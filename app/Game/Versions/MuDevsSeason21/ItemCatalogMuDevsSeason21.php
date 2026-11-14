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

    /** @var array<string, int>|null */
    private ?array $maximumSockets = null;

    /** @var array<string, list<array{index: int, name: string}|null>>|null */
    private ?array $ancientSets = null;

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
     * Returns the maximum socket count declared by the active SocketItemType source.
     */
    public function maximumSocketsFor(int $group, int $index): int
    {
        if ($this->maximumSockets === null) {
            $catalogPath = $this->catalogPath ?? resource_path('game/mudevs_season_21/items.json');
            $socketPath = dirname($catalogPath).'/socket-items.json';
            $contents = is_readable($socketPath) ? file_get_contents($socketPath) : false;

            if ($contents === false) {
                throw new RuntimeException('The MUDevs S21 socket item catalog could not be read.');
            }

            try {
                $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('The MUDevs S21 socket item catalog contains invalid JSON.', previous: $exception);
            }

            if (! is_array($records) || $records === [] || array_is_list($records)) {
                throw new RuntimeException('The MUDevs S21 socket item catalog must contain an identity-keyed object.');
            }

            foreach ($records as $code => $maximumSockets) {
                if (! is_string($code) || ! isset($this->definitions()[$code])
                    || ! is_int($maximumSockets) || $maximumSockets < 1 || $maximumSockets > 5) {
                    throw new RuntimeException('The MUDevs S21 socket item catalog contains invalid item metadata.');
                }
            }

            $this->maximumSockets = $records;
        }

        return $this->maximumSockets[$group.':'.$index] ?? 0;
    }

    /**
     * Resolves a native ancient variant through the active set type and option sources.
     *
     * @return array{index: int, name: string}|null
     */
    public function ancientSetFor(int $group, int $index, int $variant): ?array
    {
        if ($variant < 1 || $variant > 3) {
            return null;
        }

        if ($this->ancientSets === null) {
            $catalogPath = $this->catalogPath ?? resource_path('game/mudevs_season_21/items.json');
            $ancientPath = dirname($catalogPath).'/ancient-items.json';
            $contents = is_readable($ancientPath) ? file_get_contents($ancientPath) : false;

            if ($contents === false) {
                throw new RuntimeException('The MUDevs S21 ancient item catalog could not be read.');
            }

            try {
                $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('The MUDevs S21 ancient item catalog contains invalid JSON.', previous: $exception);
            }

            if (! is_array($records) || $records === [] || array_is_list($records)) {
                throw new RuntimeException('The MUDevs S21 ancient item catalog must contain an identity-keyed object.');
            }

            foreach ($records as $code => $variants) {
                if (! is_string($code) || ! isset($this->definitions()[$code])
                    || ! is_array($variants) || ! array_is_list($variants) || count($variants) !== 3) {
                    throw new RuntimeException('The MUDevs S21 ancient item catalog contains invalid item variants.');
                }

                foreach ($variants as $set) {
                    if ($set !== null && (! is_array($set) || ! is_int($set['index'] ?? null)
                        || $set['index'] < 1 || ! is_string($set['name'] ?? null) || trim($set['name']) === '')) {
                        throw new RuntimeException('The MUDevs S21 ancient item catalog contains invalid set metadata.');
                    }
                }
            }

            $this->ancientSets = $records;
        }

        return $this->ancientSets[$group.':'.$index][$variant - 1] ?? null;
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
