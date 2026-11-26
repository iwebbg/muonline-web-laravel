<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Records who verified a transfer and prevents reusing it for another purchase. */
#[Fillable(['credit_payment_id', 'administrator_id', 'reference', 'snapshot'])]
final class ManualPaymentReceipt extends Model
{
    /**
     * Preserves the payment terms reviewed by the administrator.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
