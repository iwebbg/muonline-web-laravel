<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Stats\StatAllocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates one server-side stat allocation preview.
 */
final class PreviewCharacterStatAllocationRequest extends FormRequest
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
            'strength' => ['required', 'integer', 'min:0', 'max:32767'],
            'agility' => ['required', 'integer', 'min:0', 'max:32767'],
            'vitality' => ['required', 'integer', 'min:0', 'max:32767'],
            'energy' => ['required', 'integer', 'min:0', 'max:32767'],
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
     * Returns the validated typed point distribution.
     */
    public function allocation(): StatAllocation
    {
        return new StatAllocation(
            strength: (int) $this->validated('strength'),
            agility: (int) $this->validated('agility'),
            vitality: (int) $this->validated('vitality'),
            energy: (int) $this->validated('energy'),
        );
    }
}
