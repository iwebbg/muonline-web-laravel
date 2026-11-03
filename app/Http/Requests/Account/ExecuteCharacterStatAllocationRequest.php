<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Stats\StatAllocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one idempotent character stat allocation request.
 */
final class ExecuteCharacterStatAllocationRequest extends FormRequest
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
        $rules = [
            'characterName' => [
                'required',
                'string',
                'min:3',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'idempotencyKey' => ['required', 'uuid'],
        ];

        foreach ($this->versionProfile()->stats() as $statDefinition) {
            $rules[$statDefinition->key] = [
                'sometimes',
                'integer',
                'min:0',
                'max:'.$statDefinition->maximum,
            ];
        }

        return $rules;
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

    /**
     * Returns the validated typed point distribution.
     */
    public function allocation(): StatAllocation
    {
        $values = [];

        foreach ($this->versionProfile()->stats() as $statDefinition) {
            $values[$statDefinition->key] = (int) $this->validated(
                $statDefinition->key,
                0,
            );
        }

        return StatAllocation::fromArray($values);
    }

    /**
     * Resolves the active version profile for dynamic stat validation.
     */
    private function versionProfile(): GameVersionProfile
    {
        return app(GameVersionProfile::class);
    }
}
