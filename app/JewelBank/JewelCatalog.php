<?php

namespace App\JewelBank;

use App\Game\Data\ItemSlot;

/**
 * Defines the exact item codes accepted by the 97d+99i Jewel Bank.
 */
final class JewelCatalog
{
    /**
     * Maps supported 97d+99i item codes to stable translation slugs.
     *
     * @var array<string, string>
     */
    private const JEWEL_SLUGS = [
        '12:15' => 'chaos',
        '14:13' => 'bless',
        '14:14' => 'soul',
        '14:16' => 'life',
        '14:22' => 'creation',
    ];

    /**
     * Returns whether one decoded item is a supported physical jewel.
     */
    public function accepts(ItemSlot $item): bool
    {
        return $this->acceptsCode($item->code());
    }

    /**
     * Returns whether one catalog code belongs to the supported jewel set.
     */
    public function acceptsCode(string $code): bool
    {
        return array_key_exists($code, self::JEWEL_SLUGS);
    }

    /**
     * Returns the stable translation slug for one supported code.
     */
    public function slug(string $code): ?string
    {
        return self::JEWEL_SLUGS[$code] ?? null;
    }

    /**
     * Returns every supported code and translation slug.
     *
     * @return array<string, string>
     */
    public function definitions(): array
    {
        return self::JEWEL_SLUGS;
    }
}
