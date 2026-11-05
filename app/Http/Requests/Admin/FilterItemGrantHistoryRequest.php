<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\Administrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and normalizes administrator item-grant history filters.
 */
final class FilterItemGrantHistoryRequest extends FormRequest
{
    /**
     * Allows administrators with item-grant permission to inspect the history.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::GrantItems);
    }

    /**
     * Returns bounded and allow-listed history filter rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'history_search' => ['nullable', 'string', 'max:120'],
            'history_status' => ['nullable', Rule::enum(CharacterOperationStatus::class)],
        ];
    }

    /**
     * Returns the account identifier restored after a completed grant.
     */
    public function initialAccountId(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /**
     * Returns the trimmed account, item, reason, ID or serial search phrase.
     */
    public function historySearch(): string
    {
        return $this->string('history_search')->trim()->toString();
    }

    /**
     * Returns the selected operation state or null for the complete history.
     */
    public function historyStatus(): ?CharacterOperationStatus
    {
        return CharacterOperationStatus::tryFrom(
            $this->string('history_status')->toString(),
        );
    }
}
