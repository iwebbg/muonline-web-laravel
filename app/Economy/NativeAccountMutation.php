<?php

namespace App\Economy;

/**
 * Captures immutable native grant terms independently of mutable administrator offers.
 */
final readonly class NativeAccountMutation
{
    /**
     * Defines either a currency amount or an account VIP tier and duration.
     */
    public function __construct(
        public NativePurchaseKind $kind,
        public ?NativeCurrency $currency = null,
        public int $amount = 0,
        public ?int $characterIndex = null,
        public ?int $vipLevel = null,
        public int $durationDays = 0,
    ) {}

    /**
     * Serializes exact native grant terms for durable replay and audit.
     *
     * @return array{kind: string, currency: string|null, amount: int, character_index: int|null, vip_level: int|null, duration_days: int}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'currency' => $this->currency?->value,
            'amount' => $this->amount,
            'character_index' => $this->characterIndex,
            'vip_level' => $this->vipLevel,
            'duration_days' => $this->durationDays,
        ];
    }

    /**
     * Restores only the captured native terms when a purchase is resumed.
     *
     * @param  array{kind: string, currency: string|null, amount: int, character_index: int|null, vip_level: int|null, duration_days: int}  $snapshot
     */
    public static function fromArray(array $snapshot): self
    {
        return new self(
            kind: NativePurchaseKind::from($snapshot['kind']),
            currency: $snapshot['currency'] === null ? null : NativeCurrency::from($snapshot['currency']),
            amount: $snapshot['amount'],
            characterIndex: $snapshot['character_index'],
            vipLevel: $snapshot['vip_level'],
            durationDays: $snapshot['duration_days'],
        );
    }
}
