<?php

namespace Database\Factories;

use App\Models\WebStorageItem;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebStorageItem>
 */
final class WebStorageItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serial = pack('N', fake()->unique()->numberBetween(1, 2_000_000_000));
        $binary = "\x00\x00\x20".$serial."\x00\x00\x00";

        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'account_id' => 'storage01',
            'storage_context' => WebStorageContext::WebVault,
            'source_character_name' => 'StorageChar',
            'source_vault' => 0,
            'source_slot' => 0,
            'item_binary' => $binary,
            'item_fingerprint' => hash('sha256', $binary),
            'escrow_key' => hash('sha256', $binary),
            'item_snapshot' => [
                'slot' => 0,
                'code' => '0:0',
                'name' => 'Kris',
                'width' => 1,
                'height' => 2,
                'image' => '/images/items/97d_99i/0000.gif',
                'level' => 0,
                'rarity' => 'normal',
            ],
            'status' => WebStorageItemStatus::Available,
            'sync_attempts' => 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => null,
            'stored_at' => now(),
            'withdrawn_at' => null,
        ];
    }

    /**
     * Marks the exact item as physical Jewel Bank custody.
     */
    public function jewelBank(): static
    {
        return $this->state(fn (): array => [
            'storage_context' => WebStorageContext::JewelBank,
        ]);
    }
}
