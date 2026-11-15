<?php

namespace Tests\IntegrationSeason21;

use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\PurchasedResetPolicy;
use App\Economy\NativeAccountMutation;
use App\Economy\NativeCurrency;
use App\Economy\NativePurchaseKind;
use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\Data\BattleCoreAccountSnapshot;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountActivationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountRegistrationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountServiceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterServiceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ItemSerialRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\MuDevsSeason21\AccountPurchaseJournalMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\CharacterIdentityMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\CharacterServiceJournalMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemCodecAdapterMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Installation\GameDatabaseInstallation;
use App\Installation\SqlServerGameDatabaseInstaller;
use App\Market\MarketEscrowResult;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies the complete native runtime role against a fresh isolated SQL Server database.
 */
final class MuDevsSeason21RuntimePermissionsTest extends TestCase
{
    private const DATABASE = 'MuOnline_S21_Runtime_Test';

    private const LOGIN = 's21_runtime_user';

    private const PASSWORD = 'S21-runtime-test-only-123!';

    /**
     * Supports reviewed native writes while keeping special item payloads and receipts protected.
     */
    public function test_runtime_role_supports_native_account_and_item_services_without_admin_privileges(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $adminConfiguration = config('database.connections.game');
        $adminConfiguration['database'] = 'master';
        config()->set('database.connections.game_admin', $adminConfiguration);
        $database->purge('game_admin');
        $admin = $database->connection('game_admin')->getPdo();
        $admin->exec('CREATE DATABASE [MuOnline_S21_Runtime_Test]');

        try {
            $admin->exec('USE [MuOnline_S21_Runtime_Test]');
            $admin->exec(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-runtime-bootstrap-schema.sql')));
            $admin->exec(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-serial-procedure.sql')));
            $admin->exec('INSERT INTO GameServerInfo (Number, ItemCount) VALUES (0, 100)');
            $admin->exec("INSERT INTO MEMB_STAT (memb___id, ConnectStat) VALUES ('owner', 0)");
            $admin->exec("INSERT INTO Character (AccountID, Name, Class, cLevel, LevelUpPoint, ResetCount, MasterResetCount, ExtInventory, GiantMountType, GiantMountStatus, Leadership) VALUES ('owner', 'PaidHero', 68, 400, 100, 2, 1, 0, 0, 0, 1500)");
            $warehouseInsert = $admin->prepare("INSERT INTO warehouse (AccountID, AccountIndex, Items, Money) VALUES ('owner', 1, CONVERT(varbinary(6000), ?, 2), 0)");
            $warehouseInsert->execute([str_repeat('FF', 6000)]);
            config()->set('database.connections.game.database', self::DATABASE);
            config()->set('database.connections.game.username', self::LOGIN);
            config()->set('database.connections.game.password', self::PASSWORD);
            $database->purge('game');
            $profile = new MuDevsSeason21Profile;
            $installer = new SqlServerGameDatabaseInstaller($database, $profile);
            $installation = new GameDatabaseInstallation(self::DATABASE, self::LOGIN, self::PASSWORD, 's21_runtime_role');
            $installer->install($installation);
            $installer->install($installation);
            $runtime = $database->connection('game');
            $permissions = $runtime->selectOne(
                "SELECT IS_SRVROLEMEMBER('sysadmin') AS is_admin, IS_MEMBER('db_owner') AS is_owner, "
                ."HAS_PERMS_BY_NAME('dbo.Character', 'OBJECT', 'UPDATE') AS can_update_character, "
                ."HAS_PERMS_BY_NAME('dbo.CashShopData', 'OBJECT', 'UPDATE') AS can_update_coins, "
                ."HAS_PERMS_BY_NAME('dbo.PentagramJewel', 'OBJECT', 'UPDATE') AS can_update_special, "
                ."HAS_PERMS_BY_NAME('dbo.IWEB_S21_ItemOperation', 'OBJECT', 'DELETE') AS can_delete_receipts, "
                ."HAS_PERMS_BY_NAME('dbo.IWEB_S21_ItemOperation', 'OBJECT', 'UPDATE') AS can_update_receipts, "
                ."HAS_PERMS_BY_NAME('dbo.MEMB_STAT', 'OBJECT', 'UPDATE') AS can_change_online_state",
            );

            self::assertSame(0, (int) $permissions->is_admin);
            self::assertSame(0, (int) $permissions->is_owner);
            self::assertSame(1, (int) $permissions->can_update_character);
            self::assertSame(1, (int) $permissions->can_update_coins);
            self::assertSame(0, (int) $permissions->can_update_special);
            self::assertSame(0, (int) $permissions->can_delete_receipts);
            self::assertSame(0, (int) $permissions->can_update_receipts);
            self::assertSame(0, (int) $permissions->can_change_online_state);

            $registration = $this->app->make(SqlServerMuDevsSeason21AccountRegistrationRepository::class)
                ->register('owner', 'test-pass', 'owner@example.invalid');
            self::assertSame(1, $registration->accountGuid);
            self::assertSame(18, strlen($runtime->table('MEMB_INFO')->where('memb___id', 'owner')->value('sno__numb')));
            self::assertTrue($this->app->make(SqlServerMuDevsSeason21AccountActivationRepository::class)->activate('owner'));
            $serial = $this->app->make(SqlServerMuDevsSeason21ItemSerialRepository::class)->nextSerial();
            self::assertSame(101, $serial);
            $item = $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->create(0, 0, 0, 255, 0, false, false, 0, 0, $serial);
            $escrow = $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class);
            self::assertSame(MarketEscrowResult::Deposited, $escrow->depositForOperation('owner', $item, 1, 2, 'runtime-delivery'));
            self::assertSame(MarketEscrowResult::Extracted, $escrow->extractForOperation('owner', 0, 0, $item, 'runtime-extraction'));
            self::assertSame(2, $runtime->table('IWEB_S21_ItemOperation')->count());
            $paid = new SqlServerMuDevsSeason21CharacterServiceRepository($database, $profile, new CharacterServiceJournalMuDevsSeason21);
            $first = $paid->applyPurchaseForOperation('owner', 'PaidHero', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'runtime-paid-points');
            $repeat = $paid->applyPurchaseForOperation('owner', 'PaidHero', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'runtime-paid-points');
            self::assertSame(1100, $first->after['points']);
            self::assertSame($first->after, $repeat->after);
            $reset = $paid->applyPurchaseForOperation('owner', 'PaidHero', CharacterServiceType::ResetPurchase, 5,
                new PurchasedResetPolicy(999, true, PointsMode::Fixed, 500, InventoryPolicy::KeepAll), 'runtime-paid-resets');
            self::assertSame(7, $reset->after['resets']);
            self::assertSame(25, $reset->after['command']);
            self::assertSame(2, $runtime->table('IWEB_S21_CharacterOperation')->count());
            $receiptPermissions = $runtime->selectOne("SELECT HAS_PERMS_BY_NAME('dbo.IWEB_S21_CharacterOperation', 'OBJECT', 'UPDATE') AS can_update, HAS_PERMS_BY_NAME('dbo.IWEB_S21_CharacterOperation', 'OBJECT', 'DELETE') AS can_delete");
            self::assertSame(0, (int) $receiptPermissions->can_update);
            self::assertSame(0, (int) $receiptPermissions->can_delete);
            $accountServices = new SqlServerMuDevsSeason21AccountServiceRepository($database, new AccountPurchaseJournalMuDevsSeason21);
            $characterIndex = (int) $runtime->table('Character')->where('Name', 'PaidHero')->value('CharacterIndex');
            foreach (NativeCurrency::cases() as $currency) {
                $mutation = new NativeAccountMutation(NativePurchaseKind::CurrencyExchange, $currency, 7,
                    $currency === NativeCurrency::Ruud ? $characterIndex : null);
                $operationKey = 'runtime-coin:'.$currency->value;
                $grant = $accountServices->apply('owner', $mutation, $operationKey);
                $replay = $accountServices->apply('owner', $mutation, $operationKey);
                self::assertSame(7, $grant->after['balance']);
                self::assertSame($grant->after, $replay->after);
            }
            $vipMutation = new NativeAccountMutation(kind: NativePurchaseKind::AccountVip, vipLevel: 2, durationDays: 30);
            $vip = $accountServices->apply('owner', $vipMutation, 'runtime-vip');
            $repeatVip = $accountServices->apply('owner', $vipMutation, 'runtime-vip');
            self::assertSame(2, $vip->after['account_level']);
            self::assertSame($vip->after, $repeatVip->after);
            self::assertSame(6, $runtime->table('IWEB_S21_AccountPurchase')->count());
            $immutable = $runtime->selectOne("SELECT HAS_PERMS_BY_NAME('dbo.IWEB_S21_AccountPurchase', 'OBJECT', 'UPDATE') AS can_update, HAS_PERMS_BY_NAME('dbo.IWEB_S21_AccountPurchase', 'OBJECT', 'DELETE') AS can_delete");
            self::assertSame(0, (int) $immutable->can_update);
            self::assertSame(0, (int) $immutable->can_delete);
            $battle = $this->createStub(BattleCoreReadRepository::class);
            $battle->method('forAccount')->willReturn(new BattleCoreAccountSnapshot(true, [], 0));
            $identity = new CharacterIdentityMuDevsSeason21($profile, $battle);
            $identityServices = new SqlServerMuDevsSeason21CharacterServiceRepository($database, $profile, new CharacterServiceJournalMuDevsSeason21, $identity);
            $rename = $identityServices->renameForOperation('owner', 'PaidHero', 'Renamed', 'runtime-rename');
            $renameRepeat = $identityServices->renameForOperation('owner', 'PaidHero', 'Renamed', 'runtime-rename');
            self::assertSame('Renamed', $rename->after['name']);
            self::assertSame($rename->after, $renameRepeat->after);
            $classChange = $identityServices->changeClassForOperation('owner', 'Renamed', 4, 'runtime-class');
            self::assertSame(4, $classChange->after['class_code']);
            self::assertSame(str_repeat("\xFF", 246), $runtime->table('Character')->where('CharacterIndex', $characterIndex)->value('MagicList'));
            $columns = $runtime->selectOne("SELECT HAS_PERMS_BY_NAME('dbo.ArtifactSystem', 'OBJECT', 'UPDATE', 'Name', 'COLUMN') AS can_rename, HAS_PERMS_BY_NAME('dbo.ArtifactSystem', 'OBJECT', 'UPDATE', 'ItemInfo', 'COLUMN') AS can_edit_payload");
            self::assertSame(1, (int) $columns->can_rename);
            self::assertSame(0, (int) $columns->can_edit_payload);
            $admin->exec('DROP TABLE [dbo].[DeathExpRecovery]');
            $installer->install($installation);
            $withoutOptionalCache = $identityServices->renameForOperation('owner', 'Renamed', 'FinalHero', 'runtime-rename-no-optional-cache');
            self::assertSame('FinalHero', $withoutOptionalCache->after['name']);
            $this->app->instance(GameDatabaseProfile::class, $profile);
            config()->set('game.writes_enabled', false);
            config()->set('database.connections.game.readonly', true);
            $this->artisan('game:doctor')->assertSuccessful();
            self::assertSame(str_repeat("\xFF", 6000), $runtime->table('warehouse')->where('AccountID', 'owner')->value('Items'));
        } finally {
            if (isset($runtime)) {
                $runtime->disconnect();
            }
            $database->purge('game');
            $admin->exec('USE [master]');
            $admin->exec('DROP DATABASE [MuOnline_S21_Runtime_Test]');
            $admin->exec("IF SUSER_ID('s21_runtime_user') IS NOT NULL DROP LOGIN [s21_runtime_user]");
            $database->purge('game_admin');
        }
    }
}
