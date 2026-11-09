<?php

namespace App\Http\Requests\Account;

use App\Auth\GameAccount;
use App\Game\Versions\CharacterClassDefinition;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one paid class-family change request.
 */
final class ChangeCharacterClassRequest extends FormRequest
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
        $families = array_values(array_unique(array_map(
            static fn (CharacterClassDefinition $definition): string => $definition->family,
            app(GameVersionProfile::class)->classes(),
        )));

        return [
            'character_name' => ['required', 'string', 'between:1,10', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'target_family' => ['required', 'string', Rule::in($families)],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
