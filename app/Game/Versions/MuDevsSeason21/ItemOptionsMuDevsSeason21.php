<?php

namespace App\Game\Versions\MuDevsSeason21;

use Illuminate\Container\Attributes\Scoped;

/**
 * Supplies exact per-item option choices from the active S21 ItemOption configuration.
 * Shares the decoded options within one request/job without retaining them across lifecycles.
 *
 * @phpstan-type NativeOptions array{skill: bool, luck: bool, additional: bool, excellentMask: int, additionalLabel: string|null, additionalValue: int, excellent: list<array{bit: int, label: string}>, additionalVariants?: list<array{mask: int, name: string, label: string, value: int}>}
 */
#[Scoped]
final class ItemOptionsMuDevsSeason21
{
    /** @var array<string, NativeOptions>|null */
    private ?array $definitions = null;

    /**
     * Returns the native option declarations or a choice-free definition for undeclared items.
     *
     * @return NativeOptions
     */
    public function definitionFor(int $group, int $index, int $excellentMask = 0): array
    {
        $this->definitions ??= json_decode(
            file_get_contents(resource_path('game/mudevs_season_21/construction-options.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $definition = $this->definitions[$group.':'.$index] ?? [
            'skill' => false, 'luck' => false, 'additional' => false, 'excellentMask' => 0,
            'additionalLabel' => null, 'additionalValue' => 0, 'excellent' => [],
        ];

        foreach ($definition['additionalVariants'] ?? [] as $variant) {
            if ($variant['mask'] > 0 && ($excellentMask & $variant['mask']) !== 0) {
                $definition['additionalLabel'] = $variant['label'];
                $definition['additionalValue'] = $variant['value'];

                break;
            }
        }

        return $definition;
    }
}
