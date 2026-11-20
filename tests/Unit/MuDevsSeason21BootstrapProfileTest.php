<?php

namespace Tests\Unit;

use App\Game\Versions\MuDevsSeason21\BattleCoreDatabaseProfileMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\CharacterIdentityMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ReadOnlyDatabaseProfileMuDevsSeason21;
use PHPUnit\Framework\TestCase;

/**
 * Prevents commissioning profiles from granting game mutations or unrelated access.
 */
final class MuDevsSeason21BootstrapProfileTest extends TestCase
{
    /**
     * Confirms main-game bootstrap grants only SELECT to the reviewed source tables.
     */
    public function test_main_game_commissioning_does_not_grant_writes_or_serial_execution(): void
    {
        $profile = new ReadOnlyDatabaseProfileMuDevsSeason21;
        $permissions = $profile->databaseObjectPermissions();

        $expectedObjects = [
            'dbo.MEMB_INFO', 'dbo.MEMB_STAT', 'dbo.AccountCharacter', 'dbo.Character',
            'dbo.warehouse', 'dbo.ExtWarehouse', 'dbo.MuCastle_DATA', 'dbo.Guild', 'dbo.GuildMember',
            'dbo.MasterSkillTree', 'dbo.EnhanceSkillTree', 'dbo.AbilityEnhancementCore',
            'dbo.CashShopData', 'dbo.LuckyCoin', 'dbo.Gens_Rank', 'dbo.PentagramJewel',
            'dbo.MuunInventory', 'dbo.EventInventory', 'dbo.GremoryCase',
            'dbo.ArtifactSystem', 'dbo.WingCoreItem', 'dbo.IWEB_S21_AccountPurchase', 'dbo.IWEB_S21_CharacterOperation', 'dbo.IWEB_S21_ItemOperation',
        ];
        foreach (array_keys(CharacterIdentityMuDevsSeason21::requiredSchema()) as $table) {
            $expectedObjects[] = 'dbo.'.$table;
        }
        self::assertSame(array_values(array_unique($expectedObjects)), array_keys($permissions));

        foreach ($permissions as $object => $grants) {
            self::assertSame(['SELECT'], $grants, $object);
        }
    }

    /**
     * Confirms event access cannot execute registration or reward mutation procedures.
     */
    public function test_battle_core_commissioning_reads_only_the_five_event_sources(): void
    {
        self::assertSame([
            'dbo.BattleCoreInfo' => ['SELECT'],
            'dbo.BattleCoreUser' => ['SELECT'],
            'dbo.BattleCoreReward' => ['SELECT'],
            'dbo.DevilSquareFinal_Ranking' => ['SELECT'],
            'dbo.DevilSquareFinal_Scores' => ['SELECT'],
        ], (new BattleCoreDatabaseProfileMuDevsSeason21)->databaseObjectPermissions());
    }

    /**
     * Confirms schema commissioning checks S21 identities and native currency columns.
     */
    public function test_main_schema_requires_s21_identity_and_currency_fields(): void
    {
        $schema = (new ReadOnlyDatabaseProfileMuDevsSeason21)->requiredDatabaseSchema();

        self::assertContains('CharacterIndex', $schema['Character']);
        self::assertContains('AccountIndex', $schema['AccountCharacter']);
        self::assertContains('AccountIndex', $schema['warehouse']);
        self::assertContains('RuudMoney', $schema['Character']);
        self::assertSame(['AccountID', 'WCoinC', 'WCoinP', 'GoblinPoint'], $schema['CashShopData']);
        self::assertNotContains('WarehouseCount', $schema['MEMB_INFO']);
    }
}
