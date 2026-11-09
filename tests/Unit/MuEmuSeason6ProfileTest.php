<?php

namespace Tests\Unit;

use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the MuEMU Season 6 Update 32 profile contract in isolation.
 */
final class MuEmuSeason6ProfileTest extends TestCase
{
    /**
     * Confirms the profile has a vendor-specific stable configuration key.
     */
    public function test_exposes_muemu_season_6_profile_identity(): void
    {
        $profile = new MuEmuSeason6Profile;

        $this->assertSame('muemu_season_6', $profile->key());
        $this->assertTrue($profile->capabilities()->grandResets);
        $this->assertTrue($profile->capabilities()->characterVip);
        $this->assertTrue($profile->capabilities()->itemGenerator);
        $this->assertTrue($profile->capabilities()->castleSiege);
        $this->assertFalse($profile->capabilities()->billingRecord);
    }

    /**
     * Confirms every verified MuEMU class code resolves to the expected name.
     */
    #[DataProvider('classDefinitions')]
    public function test_resolves_verified_class_codes(int $classCode, string $className): void
    {
        $classDefinition = (new MuEmuSeason6Profile)->classForCode($classCode);

        $this->assertSame($className, $classDefinition?->name);
    }

    /**
     * Confirms the seven Season 6 families are available to version-aware shops.
     */
    public function test_exposes_all_seven_shop_class_families(): void
    {
        $scopeValues = array_column((new MuEmuSeason6Profile)->shopClassScopes(), 'value');

        $this->assertSame([
            'all', 'dw_sm', 'dk_bk', 'elf_me', 'mg', 'dl_le', 'su_bs', 'rf_fm',
        ], $scopeValues);
    }

    /**
     * Confirms Rage Fighter defaults come from MuEMU DefaultClassInfo.txt.
     */
    public function test_returns_verified_rage_fighter_base_stats(): void
    {
        $baseStats = (new MuEmuSeason6Profile)->baseStatsForClass(98);

        $this->assertSame([
            'strength' => 32,
            'agility' => 27,
            'vitality' => 25,
            'energy' => 20,
        ], $baseStats->toArray());
    }

    /**
     * Confirms only the Dark Lord family exposes Leadership as Command.
     */
    public function test_exposes_command_only_for_dark_lord_family(): void
    {
        $profile = new MuEmuSeason6Profile;

        $this->assertSame(
            ['strength', 'agility', 'vitality', 'energy'],
            array_column($profile->statsForClass(82), 'key'),
        );
        $this->assertSame(
            ['strength', 'agility', 'vitality', 'energy', 'command'],
            array_column($profile->statsForClass(66), 'key'),
        );
    }

    /**
     * Confirms MuEMU-specific reset, extended storage and online fields are required.
     */
    public function test_declares_verified_muemu_schema_differences(): void
    {
        $schema = (new MuEmuSeason6Profile)->requiredDatabaseSchema();

        $this->assertContains('ResetCount', $schema['Character']);
        $this->assertContains('MasterResetCount', $schema['Character']);
        $this->assertContains('Inventory', $schema['Character']);
        $this->assertContains('GameID10', $schema['AccountCharacter']);
        $this->assertContains('OnlineHours', $schema['MEMB_STAT']);
        $this->assertSame(['AccountID', 'Items', 'Money', 'Number'], $schema['ExtWarehouse']);
    }

    /**
     * Confirms unsupported class codes fail before reaching persistence.
     */
    public function test_rejects_unsupported_class_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MuEMU Season 6 character class code: 97.');

        (new MuEmuSeason6Profile)->statsForClass(97);
    }

    /**
     * Provides every verified MuEMU Season 6 class evolution.
     *
     * @return array<string, array{int, string}>
     */
    public static function classDefinitions(): array
    {
        return [
            'dark wizard' => [0, 'Dark Wizard'],
            'soul master' => [1, 'Soul Master'],
            'grand master' => [2, 'Grand Master'],
            'dark knight' => [16, 'Dark Knight'],
            'blade knight' => [17, 'Blade Knight'],
            'blade master' => [18, 'Blade Master'],
            'fairy elf' => [32, 'Fairy Elf'],
            'muse elf' => [33, 'Muse Elf'],
            'high elf' => [34, 'High Elf'],
            'magic gladiator' => [48, 'Magic Gladiator'],
            'duel master' => [50, 'Duel Master'],
            'dark lord' => [64, 'Dark Lord'],
            'lord emperor' => [66, 'Lord Emperor'],
            'summoner' => [80, 'Summoner'],
            'bloody summoner' => [81, 'Bloody Summoner'],
            'dimension master' => [82, 'Dimension Master'],
            'rage fighter' => [96, 'Rage Fighter'],
            'fist master' => [98, 'Fist Master'],
        ];
    }
}
