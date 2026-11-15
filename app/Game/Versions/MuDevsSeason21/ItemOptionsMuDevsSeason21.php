<?php

namespace App\Game\Versions\MuDevsSeason21;

/**
 * Supplies exact per-item option choices from the active S21 ItemOption configuration.
 *
 * @phpstan-type NativeOptions array{skill: bool, luck: bool, additional: bool, excellentMask: int, additionalLabel: string|null, additionalValue: int, excellent: list<array{bit: int, label: string}>}
 */
final class ItemOptionsMuDevsSeason21
{
    /** @var array<string, NativeOptions>|null */
    private ?array $definitions = null;

    /**
     * Returns the native option declarations or a choice-free definition for undeclared items.
     *
     * @return NativeOptions
     */
    public function definitionFor(int $group, int $index): array
    {
        $this->definitions ??= json_decode(
            file_get_contents(resource_path('game/mudevs_season_21/construction-options.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $this->definitions[$group.':'.$index] ?? [
            'skill' => false, 'luck' => false, 'additional' => false, 'excellentMask' => 0,
            'additionalLabel' => null, 'additionalValue' => 0, 'excellent' => [],
        ];
    }
}
