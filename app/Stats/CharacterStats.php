<?php

namespace App\Stats;

/**
 * Carries the four mutable MuOnline character attributes as one typed value.
 */
final readonly class CharacterStats
{
    /** @var array<string, int> */
    private array $values;

    public int $strength;

    public int $agility;

    public int $vitality;

    public int $energy;

    /**
     * Creates an immutable snapshot while retaining legacy property compatibility.
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
     * Creates a snapshot from any version-defined set of attributes.
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
     * Calculates the resulting attributes after one allocation.
     */
    public function add(StatAllocation $allocation): self
    {
        $result = $this->values;

        foreach ($allocation->toArray() as $statKey => $amount) {
            if ($amount === 0 && ! array_key_exists($statKey, $result)) {
                continue;
            }

            $result[$statKey] = ($result[$statKey] ?? 0) + $amount;
        }

        return self::fromArray($result);
    }

    /**
     * Determines whether every current attribute is at least its base value.
     */
    public function containsAtLeast(self $baseStats): bool
    {
        foreach ($baseStats->toArray() as $statKey => $baseValue) {
            if (($this->values[$statKey] ?? 0) < $baseValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the total points allocated above the supplied base attributes.
     */
    public function allocatedAbove(self $baseStats): int
    {
        $allocatedPoints = 0;

        foreach ($baseStats->toArray() as $statKey => $baseValue) {
            $allocatedPoints += ($this->values[$statKey] ?? 0) - $baseValue;
        }

        return $allocatedPoints;
    }

    /**
     * Converts the attributes to the stable client and audit shape.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
