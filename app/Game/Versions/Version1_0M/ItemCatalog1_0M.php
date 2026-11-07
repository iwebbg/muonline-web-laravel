<?php

namespace App\Game\Versions\Version1_0M;

use App\Game\Data\ItemDefinition;
use JsonException;
use RuntimeException;

/**
 * Reads item metadata owned exclusively by the DarksTeam 1.0M adapter.
 */
final class ItemCatalog1_0M
{
    private string $catalogPath;

    /** @var array<string, ItemDefinition>|null */
    private ?array $definitions = null;

    /**
     * Creates the catalog with its bundled DarksTeam 1.0M metadata source.
     */
    public function __construct(?string $catalogPath = null)
    {
        $this->catalogPath = $catalogPath
            ?? resource_path('game/1_0m/items.json');
    }

    /**
     * Returns an item definition when DarksTeam 1.0M declares it.
     */
    public function definitionFor(int $group, int $index): ?ItemDefinition
    {
        $code = $group.':'.$index;

        return $this->definitions()[$code] ?? null;
    }

    /**
     * Returns every definition ordered by native group and item index.
     *
     * @return list<ItemDefinition>
     */
    public function allDefinitions(): array
    {
        return array_values($this->definitions());
    }

    /**
     * Loads and validates the bundled catalog once for the current request.
     *
     * @return array<string, ItemDefinition>
     *
     * @throws RuntimeException When the bundled catalog cannot be read or decoded.
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $contents = file_get_contents($this->catalogPath);

        if ($contents === false) {
            throw new RuntimeException('The DarksTeam 1.0M item catalog could not be read.');
        }

        try {
            $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The DarksTeam 1.0M item catalog contains invalid JSON.',
                previous: $exception,
            );
        }

        if (! is_array($records)) {
            throw new RuntimeException('The DarksTeam 1.0M item catalog must contain an object.');
        }

        $definitions = [];

        foreach ($records as $code => $record) {
            if (! is_array($record)) {
                throw new RuntimeException("Item definition {$code} must contain an object.");
            }

            $definition = new ItemDefinition(
                group: (int) ($record['group'] ?? -1),
                index: (int) ($record['index'] ?? -1),
                name: (string) ($record['name'] ?? ''),
                width: max(1, (int) ($record['width'] ?? 1)),
                height: max(1, (int) ($record['height'] ?? 1)),
                image: isset($record['image']) ? (string) $record['image'] : null,
                excellentType: (int) ($record['excellentType'] ?? 0),
                optionType: (int) ($record['optionType'] ?? 0),
            );

            if ($definition->code() !== $code || $definition->name === '') {
                throw new RuntimeException("Item definition {$code} has invalid identity metadata.");
            }

            $definitions[$code] = $definition;
        }

        $this->definitions = $definitions;

        return $this->definitions;
    }
}
