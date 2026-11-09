<?php

namespace Tests\Integration\Game\Repositories;

use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Repositories\SqlServer97d99iCharacterServiceRepository;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies paid identity mutations against the disposable 97d+99i SQL Server schema.
 */
final class SqlServer97d99iCharacterServiceRepositoryTest extends TestCase
{
    private const ACCOUNT_ID = 'svc97test';

    /**
     * Removes only records owned by this integration test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $connection = $this->gameConnection();
            $connection->table('GuildMember')->whereIn('Name', ['Old97Hero', 'New97Hero'])->delete();
            $connection->table('OptionData')->whereIn('Name', ['Old97Hero', 'New97Hero'])->delete();
            $connection->table('Guild')->where('G_Name', 'Svc97')->delete();
            $connection->table('AccountCharacter')->where('Id', self::ACCOUNT_ID)->delete();
            $connection->table('Character')->where('AccountID', self::ACCOUNT_ID)->delete();
            $connection->table('MEMB_STAT')->where('memb___id', self::ACCOUNT_ID)->delete();
        }

        parent::tearDown();
    }

    /**
     * Confirms rename references and class codes mutate inside real SQL Server transactions.
     */
    public function test_renames_character_references_and_changes_class(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_STAT')->insert(['memb___id' => self::ACCOUNT_ID, 'ConnectStat' => 0]);
        $connection->table('Character')->insert([
            'AccountID' => self::ACCOUNT_ID,
            'Name' => 'Old97Hero',
            'Class' => 17,
            'Resets' => 0,
            'GrandResets' => 0,
        ]);
        $connection->table('AccountCharacter')->insert([
            'Id' => self::ACCOUNT_ID,
            'GameID1' => 'Old97Hero',
            'GameIDC' => 'Old97Hero',
        ]);
        $connection->table('Guild')->insert(['G_Name' => 'Svc97', 'G_Master' => 'Old97Hero']);
        $connection->table('GuildMember')->insert(['Name' => 'Old97Hero', 'G_Name' => 'Svc97']);
        $connection->table('OptionData')->insert(['Name' => 'Old97Hero']);
        $repository = new SqlServer97d99iCharacterServiceRepository(
            $this->app->make(DatabaseManager::class),
            new Version97d99iProfile,
        );

        $rename = $repository->rename(self::ACCOUNT_ID, 'Old97Hero', 'New97Hero');
        $classChange = $repository->changeClass(self::ACCOUNT_ID, 'New97Hero', 33);
        $resetPurchase = $repository->purchaseResets(
            self::ACCOUNT_ID,
            'New97Hero',
            5,
            PurchasedResetPolicy::fromResetSettings(ResetProfileSettings::defaults()),
        );
        $pointsPurchase = $repository->purchaseLevelUpPoints(self::ACCOUNT_ID, 'New97Hero', 1_000);

        $this->assertSame('New97Hero', $rename->after['name']);
        $this->assertSame(33, $classChange->after['class_code']);
        $this->assertSame(5, $resetPurchase->after['resets']);
        $this->assertSame(3_500, $pointsPurchase->after['points']);
        $this->assertSame('New97Hero', $connection->table('AccountCharacter')->where('Id', self::ACCOUNT_ID)->value('GameID1'));
        $this->assertSame('New97Hero', $connection->table('Guild')->where('G_Name', 'Svc97')->value('G_Master'));
        $this->assertTrue($connection->table('GuildMember')->where('Name', 'New97Hero')->exists());
        $this->assertTrue($connection->table('OptionData')->where('Name', 'New97Hero')->exists());
        $this->assertSame(33, (int) $connection->table('Character')->where('Name', 'New97Hero')->value('Class'));
        $this->assertSame(5, (int) $connection->table('Character')->where('Name', 'New97Hero')->value('Resets'));
        $this->assertSame(3_500, (int) $connection->table('Character')->where('Name', 'New97Hero')->value('LevelUpPoint'));
    }

    /**
     * Returns the disposable legacy game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
