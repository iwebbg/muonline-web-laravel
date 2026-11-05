<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Payments\CreditPackageStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorizes and normalizes administrator Credits package filters. */
final class FilterCreditPackagesRequest extends FormRequest
{
    /**
     * Allows economy managers to search the package catalog.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Returns bounded and allow-listed package filters.
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
                'regex:/\A[\pL\pN _.:@+\-]+\z/u',
            ],
            'status' => ['nullable', Rule::enum(CreditPackageStatus::class)],
        ];
    }

    /** Returns the trimmed English or Bulgarian package title search phrase. */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /** Returns the selected package status or null for all states. */
    public function status(): ?CreditPackageStatus
    {
        return CreditPackageStatus::tryFrom($this->string('status')->toString());
    }
}
