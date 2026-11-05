<?php

namespace App\Game\Data;

/**
 * Carries one normalized class or map segment in the realm analytics snapshot.
 */
final readonly class RealmDistributionEntry
{
    /**
     * Creates one distribution segment with the total required for its share.
     */
    public function __construct(
        public string $label,
        public int $count,
        private int $total,
        public ?string $abbreviation = null,
    ) {}

    /**
     * Converts the segment to the stable presentation shape used by the dashboard.
     *
     * @return array{label: string, count: int, percentage: float, abbreviation: string|null}
     */
    public function toArray(): array
    {
        $percentage = $this->total > 0
            ? round(($this->count / $this->total) * 100, 1)
            : 0.0;

        return [
            'label' => $this->label,
            'count' => $this->count,
            'percentage' => $percentage,
            'abbreviation' => $this->abbreviation,
        ];
    }
}
