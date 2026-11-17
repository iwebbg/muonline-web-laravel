<?php

namespace App\Game\Versions\MuEmuSeason6;

use App\Game\Contracts\ItemConstructionPolicy;
use App\Game\Contracts\ItemGrantRules;

/**
 * Defines verified item option limits for MuEMU Season 6 Update 32.
 */
final readonly class ItemGrantRulesMuEmuSeason6 implements ItemConstructionPolicy, ItemGrantRules
{
    public const MAX_ITEM_LEVEL = 15;

    public const MAX_OPTION_LEVEL = 7;

    /**
     * Reuses the verified Update 32 generator compatibility independently of prices.
     */
    public function __construct(private ItemGeneratorOptionsMuEmuSeason6 $options = new ItemGeneratorOptionsMuEmuSeason6) {}

    /**
     * Returns the same item-specific advanced choices used by the existing generator.
     *
     * @return array{ancientOptions: list<int>, allows380: bool, harmonyType: ?string, harmonyOptions: list<array{value: int, minimumLevel: int}>, maxSockets: int}
     */
    public function optionsFor(int $group, int $index): array
    {
        return $this->options->metadataFor($group, $index);
    }

    /**
     * Rejects incompatible advanced combinations before a serial is reserved or Credits are charged.
     *
     * @param  array<string, int|bool|string|null>  $options
     */
    public function allowsConstruction(array $options): bool
    {
        $identity = explode(':', (string) ($options['item_code'] ?? ''), 2);
        $group = (int) ($options['group'] ?? $identity[0] ?? -1);
        $index = (int) ($options['index'] ?? $identity[1] ?? -1);
        $ancient = (int) ($options['ancient_option'] ?? 0);

        return ($ancient === 0 || (int) ($options['excellent_mask'] ?? 0) === 0)
            && $this->options->optionsAreAllowed($group, $index, [
                'ancient_option' => $ancient,
                'harmony_option' => (int) ($options['harmony_option'] ?? 0),
                'socket_count' => (int) ($options['socket_count'] ?? 0),
                'has_380_option' => (bool) ($options['has_380_option'] ?? false),
            ]);
    }

    /**
     * Returns the highest item index represented by this native format.
     */
    public function maxItemIndex(): int
    {
        return 255;
    }

    /**
     * Returns the highest item level supported by the native item format.
     */
    public function maxItemLevel(): int
    {
        return self::MAX_ITEM_LEVEL;
    }

    /**
     * Returns the highest additional-option level encoded by the native format.
     */
    public function maxOptionLevel(): int
    {
        return self::MAX_OPTION_LEVEL;
    }

    /**
     * Returns every accepted item level.
     *
     * @return list<int>
     */
    public function itemLevels(): array
    {
        return range(0, self::MAX_ITEM_LEVEL);
    }

    /**
     * Returns every accepted additional-option level.
     *
     * @return list<int>
     */
    public function optionLevels(): array
    {
        return range(0, self::MAX_OPTION_LEVEL);
    }
}
