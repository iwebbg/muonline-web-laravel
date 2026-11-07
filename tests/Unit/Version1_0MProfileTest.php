<?php

namespace Tests\Unit;

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
    public function test_exposes_command_only_for_dark_lord_evolutions(): void
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
        $baseStats = (new Version1_0MProfile)->baseStatsForClass(65);

        $this->assertSame([
            'strength' => 26,
            'agility' => 20,
            'vitality' => 20,
            'energy' => 15,
            'command' => 25,
        ], $baseStats->toArray());
    }

    /**
     * Confirms unsupported legacy class codes fail before reaching persistence.
     */
    public function test_rejects_unsupported_class_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported 1.0M character class code: 66.');

        (new Version1_0MProfile)->statsForClass(66);
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
            'lord emperor' => [65, 'Lord Emperor'],
        ];
    }
}
