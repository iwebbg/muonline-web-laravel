<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one idempotent Move to Town request.
 */
final class ExecuteCharacterMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof GameAccount;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'characterName' => [
                'required',
                'string',
                'min:3',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'idempotencyKey' => ['required', 'uuid'],
        ];
    }

    /**
     * Returns the validated legacy character identifier.
     */
    public function characterName(): string
    {
        return (string) $this->validated('characterName');
    }

    /**
     * Returns the validated client-generated operation identifier.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotencyKey');
    }
}
