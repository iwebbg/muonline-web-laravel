<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one player item-to-Credits exchange request.
 */
final class ExchangeItemsForCreditsRequest extends FormRequest
{
    /**
     * Allows authenticated game accounts through the request boundary.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Accepts one public offer, owned legacy character and bounded quantity.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'offer_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
