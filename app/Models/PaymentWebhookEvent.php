<?php

namespace App\Models;

use App\Payments\PaymentProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Records provider events before business processing for replay safety. */
#[Fillable(['provider', 'provider_event_id', 'payload_hash', 'event_type', 'status', 'payload', 'failure_reason', 'processed_at'])]
final class PaymentWebhookEvent extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
