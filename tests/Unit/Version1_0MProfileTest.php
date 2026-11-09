<?php

namespace Tests\Unit;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Contracts\CharacterVipRepository;
use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\SqlServer1_0MCastleSiegeReadRepository;
use App\Game\Repositories\SqlServer1_0MCharacterGrandResetRepository;
use App\Game\Repositories\SqlServer1_0MCharacterVipRepository;
use App\Game\Repositories\SqlServer1_0MRealmReadRepository;
use App\Game\Repositories\SqlServerOnlineAccountRepository;
use App\Game\Versions\Version1_0M\ItemCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemCodec1_0M;
use App\Game\Versions\Version1_0M\ItemGeneratorCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemGrantRules1_0M;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the isolated DarksTeam 1.0M class and attribute contract.
 */
final class Version1_0MProfileTest extends TestCase
{
    /**
     * Confirms unsupported custom columns are disabled for the stock 1.0M schema.
     */
    public function test_declares_stock_schema_capabilities(): void
    {
        $capabilities = (new Version1_0MProfile)->capabilities();

        $this->assertTrue($capabilities->grandResets);
        $this->assertFalse($capabilities->characterVip);
        $this->assertFalse($capabilities->billingRecord);
        $this->assertTrue($capabilities->itemGenerator);
        $this->assertTrue($capabilities->castleSiege);
    }

    /**
     * Confirms 1.0M provisioning excludes the 97d-only billing table.
     */
    public function test_declares_profile_specific_schema_and_permissions(): void
    {
        $profile = new Version1_0MProfile;

        $this->assertArrayNotHasKey('VI_CURR_INFO', $profile->requiredDatabaseSchema());
        $this->assertArrayNotHasKey('dbo.VI_CURR_INFO', $profile->databaseObjectPermissions());
        $this->assertSame(
            ['SELECT', 'UPDATE'],
            $profile->databaseObjectPermissions()['dbo.Character'],
        );
        $this->assertArrayNotHasKey(
            'dbo.UFN_MD5_CHECKVALUE',
            $profile->databaseObjectPermissions(),
        );
        $this->assertArrayNotHasKey(
            'dbo.UFN_MD5_ENCODEVALUE',
            $profile->databaseObjectPermissions(),
        );
        $this->assertSame(
            ['SELECT'],
            $profile->databaseObjectPermissions()['dbo.MuCastle_DATA'],
        );
    }

    /**
     * Confirms every supported legacy class code resolves to its public name.
     */
    #[DataProvider('classDefinitions')]
    public function test_resolves_supported_class_codes(int $classCode, string $className): void
    {
        $classDefinition = (new Version1_0MProfile)->classForCode($classCode);

        $this->assertSame($className, $classDefinition?->name);
    }

    /**
     * Confirms only the Dark Lord family exposes the Command attribute.
     */
    public function test_exposes_command_only_for_dark_lord(): void
    {
        $profile = new Version1_0MProfile;

        $wizardStatKeys = array_column($profile->statsForClass(0), 'key');
        $darkLordStatKeys = array_column($profile->statsForClass(64), 'key');

        $this->assertSame(['strength', 'agility', 'vitality', 'energy'], $wizardStatKeys);
        $this->assertSame(['strength', 'agility', 'vitality', 'energy', 'command'], $darkLordStatKeys);
    }

    /**
     * Confirms the Dark Lord family starts with the DarksTeam 1.0M defaults.
     */
    public function test_returns_dark_lord_base_stats_with_command(): void
    {
        $baseStats = (new Version1_0MProfile)->baseStatsForClass(64);

        $this->assertSame([
            'strength' => 26,
            'agility' => 20,
            'vitality' => 20,
            'energy' => 15,
            'command' => 25,
        ], $baseStats->toArray());
    }

    /**
     * Confirms the 1.0M Dark Lord uses its dedicated portrait without a future evolution.
     */
    public function test_returns_dark_lord_artwork_without_lord_emperor(): void
    {
        $profile = new Version1_0MProfile;

        $this->assertSame('/images/characters/dark-lord.png', $profile->classForCode(64)?->image);
        $this->assertNull($profile->classForCode(65));
        $this->assertNull($profile->classForKey('le'));
        $this->assertFileExists(dirname(__DIR__, 2).'/public/images/characters/dark-lord.png');
    }

    /**
     * Confirms stored Dark Lord shop offers keep their stable legacy scope key.
     */
    public function test_keeps_dark_lord_shop_scope_compatible_without_lord_emperor_label(): void
    {
        $darkLordScope = collect((new Version1_0MProfile)->shopClassScopes())
            ->firstWhere('value', 'dl_le');

        $this->assertNotNull($darkLordScope);
        $this->assertSame('Dark Lord (DL)', $darkLordScope->label);
    }

    /**
     * Confirms unsupported legacy class codes fail before reaching persistence.
     */
    public function test_rejects_unsupported_class_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported 1.0M character class code: 65.');

        (new Version1_0MProfile)->statsForClass(65);
    }

    /**
     * Confirms the profile installs one complete adapter bundle without 97d fallbacks.
     */
    public function test_exposes_only_isolated_1_0m_repository_bindings(): void
    {
        $bindings = (new Version1_0MProfile)->repositoryBindings();

        $this->assertCount(23, $bindings);
        $this->assertSame(SqlServer1_0MRealmReadRepository::class, $bindings[RealmReadRepository::class]);
        $this->assertSame(
            SqlServer1_0MCharacterGrandResetRepository::class,
            $bindings[CharacterGrandResetRepository::class],
        );
        $this->assertSame(
            SqlServer1_0MCharacterVipRepository::class,
            $bindings[CharacterVipRepository::class],
        );
        $this->assertSame(
            SqlServer1_0MCastleSiegeReadRepository::class,
            $bindings[CastleSiegeReadRepository::class],
        );
        $this->assertSame(
            SqlServerOnlineAccountRepository::class,
            $bindings[OnlineAccountRepository::class],
        );

        foreach ($bindings as $adapter) {
            if ($adapter !== SqlServerOnlineAccountRepository::class) {
                $this->assertStringContainsString('1_0M', $adapter);
            }

            $this->assertStringNotContainsString('97d99i', $adapter);
        }
    }

    /**
     * Confirms item services never resolve a 97d implementation under 1.0M.
     */
    public function test_exposes_only_isolated_1_0m_item_service_bindings(): void
    {
        $bindings = (new Version1_0MProfile)->itemServiceBindings();

        $this->assertSame(ItemCatalog1_0M::class, $bindings[ItemCatalog::class]);
        $this->assertSame(ItemCodec1_0M::class, $bindings[ItemCodec::class]);
        $this->assertSame(ItemGrantRules1_0M::class, $bindings[ItemGrantRules::class]);
        $this->assertSame(ItemGeneratorCatalog1_0M::class, $bindings[ItemGeneratorCatalog::class]);

        foreach ($bindings as $adapter) {
            $this->assertStringNotContainsString('97d99i', $adapter);
        }
    }

    /**
     * Provides the complete DarksTeam 1.0M legacy class mapping.
     *
     * @return array<string, array{int, string}>
     */
    public static function classDefinitions(): array
    {
        return [
            'dark wizard' => [0, 'Dark Wizard'],
            'soul master' => [1, 'Soul Master'],
            'dark knight' => [16, 'Dark Knight'],
            'blade knight' => [17, 'Blade Knight'],
            'fairy elf' => [32, 'Fairy Elf'],
            'muse elf' => [33, 'Muse Elf'],
            'magic gladiator' => [48, 'Magic Gladiator'],
            'dark lord' => [64, 'Dark Lord'],
        ];
    }
}
