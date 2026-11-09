<?php

namespace Tests\Feature;

use App\CreditEarning\Actions\ClaimOnlineTimeCredits;
use App\CreditEarning\Actions\CollectOnlineTimeRewards;
use App\CreditEarning\Actions\ExchangeItemsForCredits;
use App\CreditEarning\ItemExchangeMatcher;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\OnlineAccountSession;
use App\Market\MarketEscrowResult;
use App\Models\AccountWallet;
use App\Models\CreditExchangeOffer;
use App\Models\EconomySetting;
use App\Models\OnlineTimeAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies exact item signatures and bounded, claimable online-time rewards.
 */
final class CreditEarningTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms an exchange offer requires every item option to match exactly.
     */
    public function test_item_exchange_matches_the_complete_item_signature(): void
    {
        $offer = CreditExchangeOffer::factory()->make([
            'item_group' => 6,
            'item_index' => 0,
            'level' => 7,
            'has_luck' => true,
            'excellent_mask' => 10,
        ]);
        $matcher = app(ItemExchangeMatcher::class);

        $this->assertTrue($matcher->matches($this->leatherPants(excellentMask: 10), $offer));
        $this->assertFalse($matcher->matches($this->leatherPants(excellentMask: 8), $offer));
    }

    /**
     * Confirms observations never backfill unknown time and a claim awards once.
     */
    public function test_online_time_is_observed_then_claimed_idempotently(): void
    {
        EconomySetting::factory()->create([
            'online_time_rewards_enabled' => true,
            'online_minutes_per_unit' => 1,
            'online_credits_per_unit' => 5,
            'online_daily_credit_limit' => 20,
        ]);
        $connectedAt = CarbonImmutable::parse('2026-11-09 10:00:00');
        $session = new OnlineAccountSession('player01', $connectedAt);
        $this->mock(OnlineAccountRepository::class, function (MockInterface $mock) use ($session): void {
            $mock->shouldReceive('onlineSessions')->twice()->andReturn([$session]);
        });
        $collector = app(CollectOnlineTimeRewards::class);

        $collector->execute($connectedAt->addMinutes(30));
        $this->assertSame(0, OnlineTimeAccount::query()->findOrFail('player01')->claimable_credits);

        $collector->execute($connectedAt->addMinutes(31));
        $this->assertSame(5, OnlineTimeAccount::query()->findOrFail('player01')->claimable_credits);

        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->with('player01')->once()->andReturnTrue();
        });
        $claimKey = (string) Str::uuid();
        $action = app(ClaimOnlineTimeCredits::class);
        $firstClaim = $action->execute('player01', $claimKey);
        $replayedClaim = $action->execute('player01', $claimKey);

        $this->assertTrue($firstClaim->is($replayedClaim));
        $this->assertSame(5, AccountWallet::query()->where('account_id', 'player01')->sole()->balance);
        $this->assertSame(0, OnlineTimeAccount::query()->findOrFail('player01')->claimable_credits);
    }

    /**
     * Confirms an exact offline item batch is removed before Credits are awarded.
     */
    public function test_exact_items_are_exchanged_for_credits_once(): void
    {
        config([
            'game.writes_enabled' => true,
            'database.connections.game.readonly' => false,
        ]);
        EconomySetting::factory()->create(['item_exchange_enabled' => true]);
        $offer = CreditExchangeOffer::factory()->create([
            'game_profile_key' => (string) config('game.profile.key'),
        ]);
        $character = new AccountCharacter(
            name: 'Exchange',
            className: 'Dark Knight',
            classCode: 16,
            image: '',
            level: 400,
            resets: 0,
            grandResets: 0,
            zen: 0,
            points: 0,
            strength: 0,
            agility: 0,
            vitality: 0,
            energy: 0,
            map: 'Lorencia',
            online: false,
        );
        $storage = new AccountItemStorage(
            character: new CharacterItemStorage('Exchange', [], [$this->leatherPants(10)]),
            warehouseZen: 0,
            vaults: [],
        );
        $this->mock(AccountReadRepository::class, function (MockInterface $mock) use ($character): void {
            $mock->shouldReceive('characterFor')->with('player01', 'Exchange')->once()->andReturn($character);
        });
        $this->mock(ItemStorageRepository::class, function (MockInterface $mock) use ($storage): void {
            $mock->shouldReceive('forAccountAndCharacter')->with('player01', 'Exchange')->once()->andReturn($storage);
        });
        $this->mock(CharacterInventoryEscrowRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('extractMany')->with('player01', 'Exchange', \Mockery::on(
                static fn (array $items): bool => count($items) === 1 && $items[0]->slot === 12,
            ))->once()->andReturn(MarketEscrowResult::Extracted);
        });
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->with('player01')->once()->andReturnTrue();
        });
        $key = (string) Str::uuid();
        $action = app(ExchangeItemsForCredits::class);
        $exchange = $action->execute('player01', 'Exchange', $offer->public_id, 1, $key);
        $replay = $action->execute('player01', 'Exchange', $offer->public_id, 1, $key);

        $this->assertTrue($exchange->is($replay));
        $this->assertSame(50, AccountWallet::query()->where('account_id', 'player01')->sole()->balance);
        $this->assertSame('completed', $exchange->status->value);
    }

    /**
     * Builds Leather Pants +7 + Luck with a caller-selected exact excellent mask.
     */
    private function leatherPants(int $excellentMask): ItemSlot
    {
        return new ItemSlot(
            slot: 12,
            binary: str_repeat("\0", 10),
            group: 6,
            index: 0,
            level: 7,
            durability: 20,
            optionLevel: 0,
            hasSkill: false,
            hasLuck: true,
            excellentMask: $excellentMask,
            ancientOption: 0,
            serial: '00000001',
            name: 'Leather Pants',
            excellentType: 1,
        );
    }
}
