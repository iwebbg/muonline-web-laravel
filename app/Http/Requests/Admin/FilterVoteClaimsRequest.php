<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\VoteRewards\VoteClaimStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and normalizes administrator vote-claim list filters.
 */
final class FilterVoteClaimsRequest extends FormRequest
{
    /**
     * Allows economy managers to inspect the claim review queue.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Returns bounded filters suitable for account and destination lookup.
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
                'regex:/\A[\pL\pN _.-]+\z/u',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    VoteClaimStatus::Pending->value,
                    VoteClaimStatus::Rewarded->value,
                    VoteClaimStatus::Rejected->value,
                ]),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Returns the trimmed account or destination search phrase.
     */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /**
     * Returns the selected review state or null for the complete queue.
     */
    public function status(): ?VoteClaimStatus
    {
        return VoteClaimStatus::tryFrom($this->string('status')->toString());
    }

    /**
     * Returns the inclusive lower boundary in the application timezone.
     */
    public function dateFrom(): ?CarbonImmutable
    {
        $date = $this->string('date_from')->toString();

        if ($date === '') {
            return null;
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }

    /**
     * Returns the inclusive upper boundary in the application timezone.
     */
    public function dateTo(): ?CarbonImmutable
    {
        $date = $this->string('date_to')->toString();

        if ($date === '') {
            return null;
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->endOfDay();
    }
}
