<?php

namespace App\Game\Versions\MuDevsSeason21;

use JsonException;
use RuntimeException;

/**
 * Reads the supplied S21 Item.xml snapshot without coercing its options into S6 types.
 *
 * This native metadata catalog deliberately remains separate from the shared
 * ItemCatalog contract until S21 option presentation and storage decoding are verified.
 */
final class ItemCatalogMuDevsSeason21
{
    /** @var array<string, ItemMetadataMuDevsSeason21>|null */
    private ?array $definitions = null;

    /**
     * Selects the bundled source snapshot or a sanitized test catalog.
     */
    public function __construct(private ?string $catalogPath = null) {}

    /**
     * Finds a declared native item without truncating indices above 255.
     */
    public function definitionFor(int $group, int $index): ?ItemMetadataMuDevsSeason21
    {
        return $this->definitions()[$group.':'.$index] ?? null;
    }

    /**
     * Returns the complete source catalog in section/index order.
     *
     * @return list<ItemMetadataMuDevsSeason21>
     */
    public function allDefinitions(): array
    {
        return array_values($this->definitions());
    }

    /**
     * Validates the bundled native metadata before caching it for this instance.
     *
     * @return array<string, ItemMetadataMuDevsSeason21>
     *
     * @throws RuntimeException When the snapshot is unreadable or malformed.
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $path = $this->catalogPath ?? resource_path('game/mudevs_season_21/items.json');
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException('The MUDevs S21 item catalog could not be read.');
        }

        try {
            $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The MUDevs S21 item catalog contains invalid JSON.', previous: $exception);
        }

        if (! is_array($records) || $records === [] || array_is_list($records)) {
            throw new RuntimeException('The MUDevs S21 item catalog must contain an identity-keyed object.');
        }

        $definitions = [];

        foreach ($records as $code => $attributes) {
            if (! is_string($code) || ! preg_match('/^(\d+):(\d+)$/D', $code, $identity) || ! is_array($attributes)) {
                throw new RuntimeException('The MUDevs S21 item catalog contains an invalid item identity.');
            }

            foreach ($attributes as $attribute => $value) {
                if (! is_string($attribute) || ! is_string($value)) {
                    throw new RuntimeException("MUDevs S21 item {$code} must preserve string-valued source attributes.");
                }
            }

            foreach (['Index', 'Width', 'Height'] as $attribute) {
                if (! isset($attributes[$attribute]) || ! ctype_digit($attributes[$attribute])) {
                    throw new RuntimeException("MUDevs S21 item {$code} has invalid {$attribute} metadata.");
                }
            }

            if (! isset($attributes['Slot']) || ! preg_match('/^-?\d+$/D', $attributes['Slot'])) {
                throw new RuntimeException("MUDevs S21 item {$code} has invalid equipment slot metadata.");
            }

            $definition = new ItemMetadataMuDevsSeason21(
                group: (int) $identity[1],
                index: (int) $attributes['Index'],
                name: $attributes['Name'] ?? '',
                width: (int) $attributes['Width'],
                height: (int) $attributes['Height'],
                equipmentSlot: (int) $attributes['Slot'],
                sourceAttributes: $attributes,
            );

            if ($definition->code() !== $code || trim($definition->name) === ''
                || $definition->width < 1 || $definition->height < 1) {
                throw new RuntimeException("MUDevs S21 item {$code} has invalid identity or geometry metadata.");
            }

            $definitions[$code] = $definition;
        }

        $this->definitions = $definitions;

        return $this->definitions;
    }
}
