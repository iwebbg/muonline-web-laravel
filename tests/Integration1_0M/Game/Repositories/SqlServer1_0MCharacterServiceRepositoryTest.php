<?php

namespace Tests\Integration1_0M\Game\Repositories;

use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Repositories\SqlServer1_0MCharacterServiceRepository;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies paid identity mutations against the disposable DarksTeam 1.0M schema.
 */
final class SqlServer1_0MCharacterServiceRepositoryTest extends TestCase
{
    private const ACCOUNT_ID = 'svc1mtest';

    /**
     * Removes only records owned by this integration test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $connection = $this->gameConnection();
            $connection->table('GuildMember')->whereIn('Name', ['Old1MHero', 'New1MHero'])->delete();
            $connection->table('Guild')->where('G_Name', 'Svc1M')->delete();
            $connection->table('AccountCharacter')->where('Id', self::ACCOUNT_ID)->delete();
            $connection->table('Character')->where('AccountID', self::ACCOUNT_ID)->delete();
            $connection->table('MEMB_STAT')->where('memb___id', self::ACCOUNT_ID)->delete();
        }

        parent::tearDown();
    }

    /**
     * Confirms core references and Dark Lord codes mutate in real SQL Server transactions.
     */
    public function test_renames_character_references_and_changes_to_dark_lord(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_STAT')->insert(['memb___id' => self::ACCOUNT_ID, 'ConnectStat' => 0]);
        $connection->table('Character')->insert([
            'AccountID' => self::ACCOUNT_ID,
            'Name' => 'Old1MHero',
            'Class' => 17,
            'Resets' => 0,
            'GrandResets' => 0,
        ]);
        $connection->table('AccountCharacter')->insert([
            'Id' => self::ACCOUNT_ID,
            'GameID1' => 'Old1MHero',
            'GameIDC' => 'Old1MHero',
        ]);
        $connection->table('Guild')->insert(['G_Name' => 'Svc1M', 'G_Master' => 'Old1MHero']);
        $connection->table('GuildMember')->insert(['Name' => 'Old1MHero', 'G_Name' => 'Svc1M']);
        $repository = new SqlServer1_0MCharacterServiceRepository(
            $this->app->make(DatabaseManager::class),
            new Version1_0MProfile,
        );

        $rename = $repository->rename(self::ACCOUNT_ID, 'Old1MHero', 'New1MHero');
        $classChange = $repository->changeClass(self::ACCOUNT_ID, 'New1MHero', 64);
        $resetPurchase = $repository->purchaseResets(
            self::ACCOUNT_ID,
            'New1MHero',
            5,
            PurchasedResetPolicy::fromResetSettings(ResetProfileSettings::defaults()),
        );
        $pointsPurchase = $repository->purchaseLevelUpPoints(self::ACCOUNT_ID, 'New1MHero', 1_000);

        $this->assertSame('New1MHero', $rename->after['name']);
        $this->assertSame(64, $classChange->after['class_code']);
        $this->assertSame(5, $resetPurchase->after['resets']);
        $this->assertSame(3_500, $pointsPurchase->after['points']);
        $this->assertSame('New1MHero', $connection->table('AccountCharacter')->where('Id', self::ACCOUNT_ID)->value('GameID1'));
        $this->assertSame('New1MHero', $connection->table('Guild')->where('G_Name', 'Svc1M')->value('G_Master'));
        $this->assertTrue($connection->table('GuildMember')->where('Name', 'New1MHero')->exists());
        $this->assertSame(64, (int) $connection->table('Character')->where('Name', 'New1MHero')->value('Class'));
        $this->assertSame(5, (int) $connection->table('Character')->where('Name', 'New1MHero')->value('Resets'));
        $this->assertSame(3_500, (int) $connection->table('Character')->where('Name', 'New1MHero')->value('LevelUpPoint'));
    }

    /**
     * Returns the disposable 1.0M game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
