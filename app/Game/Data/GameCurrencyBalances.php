<?php

namespace App\Game\Data;

/**
 * Keeps native account coins and character Ruud separate from website Credits.
 */
final readonly class GameCurrencyBalances
{
    /**
     * Creates a private account snapshot without exposing other character data.
     *
     * @param  array<string, int>  $ruudByCharacter
     */
    public function __construct(
        public int $wCoinC,
        public int $wCoinP,
        public int $goblinPoints,
        public int $luckyCoins,
        public array $ruudByCharacter,
    ) {}

    /**
     * Serializes native balances without combining them into a web wallet amount.
     *
     * @return array{wCoinC: int, wCoinP: int, goblinPoints: int, luckyCoins: int, ruudByCharacter: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'wCoinC' => $this->wCoinC,
            'wCoinP' => $this->wCoinP,
            'goblinPoints' => $this->goblinPoints,
            'luckyCoins' => $this->luckyCoins,
            'ruudByCharacter' => $this->ruudByCharacter,
        ];
    }
}
