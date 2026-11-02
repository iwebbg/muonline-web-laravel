<?php

namespace App\Http\Requests\Admin;

use App\Models\Administrator;
use App\Moderation\ModerationAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and validates one idempotent account moderation request.
 */
final class ModerateAccountRequest extends FormRequest
{
    /**
     * Allows only authenticated application administrator identities.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Administrator;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'targetIdentifier' => [
                'required',
                'string',
                'min:4',
                'max:10',
                'regex:/\A[A-Za-z0-9_-]+\z/',
            ],
            'action' => ['required', Rule::enum(ModerationAction::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'idempotencyKey' => ['required', 'uuid'],
        ];
    }

    /**
     * Returns the validated legacy account identifier.
     */
    public function targetIdentifier(): string
    {
        return (string) $this->validated('targetIdentifier');
    }

    /**
     * Returns the validated moderation action.
     */
    public function moderationAction(): ModerationAction
    {
        return ModerationAction::from((string) $this->validated('action'));
    }

    /**
     * Returns the validated administrator reason without surrounding whitespace.
     */
    public function moderationReason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    /**
     * Returns the validated client operation identifier.
     */
    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotencyKey');
    }
}
