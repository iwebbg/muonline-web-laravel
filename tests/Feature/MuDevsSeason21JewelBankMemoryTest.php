<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Market\MarketEscrowResult;
use App\Models\WebStorageItem;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Reproduces a native Jewel Bank deposit with the web request's 256 MB memory budget.
 */
final class MuDevsSeason21JewelBankMemoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Deposits Creation from native inventory without repeated full catalog allocations.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_native_creation_deposit_fits_the_web_memory_limit(): void
    {
        ini_set('memory_limit', '256M');
        $profile = new MuDevsSeason21Profile;
        $this->app->instance(GameVersionProfile::class, $profile);
        config()->set('game.profile.key', $profile->key());
        config()->set('game.profile.version', 'S21.1-2');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);

        foreach ([...$profile->repositoryBindings(), ...$profile->itemServiceBindings()] as $contract => $adapter) {
            $this->app->bind($contract, $adapter);
        }

        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
        $binary = hex2bin('160001010203040000E0FFFFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $inventory = substr_replace(str_repeat("\xFF", 239 * 25), $binary, 12 * 25, 25);
        $connection->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'JewelHero', 'CharacterIndex' => 101,
            'Class' => 50, 'cLevel' => 400, 'Inventory' => $inventory, 'ExtInventory' => 0,
        ]);
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->mock(CharacterInventoryEscrowRepository::class)
            ->shouldReceive('extract')->once()
            ->with('owner', 'JewelHero', 0, $binary)
            ->andReturn(MarketEscrowResult::Extracted);
        $account = new GameAccount(1, 'owner', 'Owner', null, false, false, 'secret1');
        $token = 's21-creation-memory';

        $response = $this->actingAs($account)
            ->withSession(['_token' => $token])
            ->from('/account/jewel-bank')
            ->post('/account/jewel-bank', [
                '_token' => $token,
                'character_name' => 'JewelHero',
                'source_location' => 'inventory',
                'vault' => 0,
                'jewel_code' => '14:22',
                'jewel_level' => 0,
                'quantity' => 1,
                'idempotency_key' => 'b976c040-67c0-4417-9281-7c195e2f8f40',
            ]);

        $response->assertRedirect('/account/jewel-bank')->assertSessionHasNoErrors();
        $stored = WebStorageItem::query()->sole();
        $this->assertSame(WebStorageContext::JewelBank, $stored->storage_context);
        $this->assertSame(WebStorageItemStatus::Available, $stored->status);
        $this->assertSame('14:22', $stored->item_snapshot['code']);
        $this->assertSame($binary, $stored->item_binary);
        $this->assertLessThan(256 * 1024 * 1024, memory_get_peak_usage(true));
    }
}
