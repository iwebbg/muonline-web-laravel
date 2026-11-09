<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one online-time Credits claim.
 */
final class ClaimOnlineTimeCreditsRequest extends FormRequest
{
    /**
     * Allows authenticated game accounts through the claim boundary.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Requires one replay-safe claim key.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
