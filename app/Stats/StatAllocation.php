<?php

namespace App\Stats;

/**
 * Carries one requested distribution of available character points.
 */
final readonly class StatAllocation
{
    /** @var array<string, int> */
    private array $values;

    public int $strength;

    public int $agility;

    public int $vitality;

    public int $energy;

    /**
     * Creates an immutable allocation while retaining legacy property compatibility.
     *
     * @param  array<string, int>  $additional
     */
    public function __construct(
        int $strength,
        int $agility,
        int $vitality,
        int $energy,
        array $additional = [],
    ) {
        $this->strength = $strength;
        $this->agility = $agility;
        $this->vitality = $vitality;
        $this->energy = $energy;
        $this->values = [
            'strength' => $strength,
            'agility' => $agility,
            'vitality' => $vitality,
            'energy' => $energy,
            ...$additional,
        ];
    }

    /**
     * Creates an allocation from any version-defined set of attributes.
     *
     * @param  array<string, int>  $values
     */
    public static function fromArray(array $values): self
    {
        $additional = array_diff_key(
            $values,
            array_flip(['strength', 'agility', 'vitality', 'energy']),
        );

        return new self(
            strength: $values['strength'] ?? 0,
            agility: $values['agility'] ?? 0,
            vitality: $values['vitality'] ?? 0,
            energy: $values['energy'] ?? 0,
            additional: $additional,
        );
    }

    /**
     * Returns the complete number of points consumed by the allocation.
     */
    public function total(): int
    {
        return array_sum($this->values);
    }

    /**
     * Determines whether every requested amount is non-negative.
     */
    public function hasOnlyNonNegativeAmounts(): bool
    {
        foreach ($this->values as $amount) {
            if ($amount < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether positive points target a stat unavailable to the class.
     *
     * @param  list<string>  $supportedStatKeys
     */
    public function allocatesOutside(array $supportedStatKeys): bool
    {
        foreach ($this->values as $statKey => $amount) {
            if ($amount > 0 && ! in_array($statKey, $supportedStatKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether applying the allocation would exceed one stat limit.
     *
     * @param  array<string, int>  $maximumStats
     */
    public function exceedsMaximum(
        CharacterStats $currentStats,
        array $maximumStats,
    ): bool {
        $currentValues = $currentStats->toArray();

        foreach ($this->values as $statKey => $amount) {
            $maximum = $maximumStats[$statKey] ?? null;

            if ($amount > 0 && ($maximum === null || ($currentValues[$statKey] ?? 0) + $amount > $maximum)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts the allocation to the stable request and audit shape.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
