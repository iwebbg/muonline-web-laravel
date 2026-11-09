<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6AccountActivationRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountCredentialRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterVipRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ItemSerialRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ModerationRepository;
use App\Moderation\ModerationAction;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipPurchaseBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

/**
 * Verifies MuEMU account maintenance against its exported plaintext schema.
 */
final class MuEmuSeason6AccountMaintenanceRepositoryTest extends TestCase
{
    /**
     * Creates the portable account and moderation subset used by the adapters.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY, memb__pwd TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, CtlCode INTEGER NULL)');
        $connection->table('MEMB_INFO')->insert([
            'memb___id' => 'owner',
            'memb__pwd' => 'oldsecret',
            'mail_addr' => 'old@example.com',
            'bloc_code' => '1',
        ]);
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => 'owner',
            'ConnectStat' => 0,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => 'owner',
            'Name' => 'Fighter',
            'CtlCode' => 0,
        ]);
    }

    /**
     * Confirms activation and plaintext credential changes target exact columns.
     */
    public function test_activates_account_and_updates_plaintext_credentials(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $activation = new SqlServerMuEmuSeason6AccountActivationRepository($database);
        $credentials = new SqlServerMuEmuSeason6AccountCredentialRepository($database);

        self::assertTrue($activation->activate('owner'));

        $result = $credentials->update(
            accountId: 'owner',
            currentPassword: 'oldsecret',
            email: 'new@example.com',
            newPassword: 'newsecret',
        );
        $account = $database->connection('game')->table('MEMB_INFO')->first();

        self::assertTrue($result->emailChanged);
        self::assertTrue($result->passwordChanged);
        self::assertSame('0', (string) $account->bloc_code);
        self::assertSame('new@example.com', $account->mail_addr);
        self::assertSame('newsecret', $account->memb__pwd);
    }

    /**
     * Confirms recovery resolves an email case-insensitively and replaces the password.
     */
    public function test_recovers_plaintext_password_for_verified_recipient(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $repository = new SqlServerMuEmuSeason6AccountPasswordRecoveryRepository($database);

        $recipient = $repository->findRecipient('owner', 'OLD@EXAMPLE.COM');

        self::assertNotNull($recipient);
        self::assertSame('old@example.com', $recipient->email);

        $repository->replacePassword('owner', 'recovered');

        self::assertSame(
            'recovered',
            $database->connection('game')->table('MEMB_INFO')->value('memb__pwd'),
        );
    }

    /**
     * Confirms account and offline-character moderation use MuEMU control fields.
     */
    public function test_moderates_account_and_offline_character(): void
    {
        $repository = new SqlServerMuEmuSeason6ModerationRepository(
            $this->app->make(DatabaseManager::class),
        );

        $account = $repository->moderateAccount('owner', ModerationAction::Unblock);
        $character = $repository->moderateCharacter('Fighter', ModerationAction::Block);

        self::assertFalse($account->afterBlocked);
        self::assertTrue($character->afterBlocked);
        self::assertSame(
            1,
            (int) $this->app->make(DatabaseManager::class)
                ->connection('game')
                ->table('Character')
                ->value('CtlCode'),
        );
    }

    /**
     * Confirms unsupported per-character VIP writes fail before database access.
     */
    public function test_rejects_per_character_vip_for_account_level_muemu_schema(): void
    {
        $repository = new SqlServerMuEmuSeason6CharacterVipRepository;

        try {
            $repository->synchronize('owner', 'Fighter', now()->addMonth());
            self::fail('MuEMU account-level VIP must not receive a character-level write.');
        } catch (VipPurchaseRejected $exception) {
            self::assertSame(VipPurchaseBlockReason::PlanUnavailable, $exception->reason);
        }
    }

    /**
     * Confirms item serials are reserved through MuEMU's server-owned procedure.
     */
    public function test_reserves_item_serial_through_muemu_procedure(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('EXEC dbo.WZ_GetItemSerial')
            ->andReturn([(object) ['ItemSerial' => 1_234_567]]);
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')
            ->once()
            ->with('game')
            ->andReturn($connection);
        $repository = new SqlServerMuEmuSeason6ItemSerialRepository($database);

        self::assertSame(1_234_567, $repository->nextSerial());
    }
}
