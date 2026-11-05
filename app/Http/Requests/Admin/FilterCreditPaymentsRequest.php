<?php

namespace App\Http\Requests\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorizes and normalizes administrator Credits purchase filters. */
final class FilterCreditPaymentsRequest extends FormRequest
{
    /**
     * Allows economy managers to inspect provider purchase history.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ManageEconomy);
    }

    /**
     * Returns bounded and allow-listed purchase-history filters.
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
                'regex:/\A[\pL\pN _.:@-]+\z/u',
            ],
            'status' => ['nullable', Rule::enum(CreditPaymentStatus::class)],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /** Returns the trimmed account or provider reference search phrase. */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /** Returns the selected payment status or null for all states. */
    public function status(): ?CreditPaymentStatus
    {
        return CreditPaymentStatus::tryFrom($this->string('status')->toString());
    }

    /** Returns the selected payment provider or null for all providers. */
    public function provider(): ?PaymentProvider
    {
        return PaymentProvider::tryFrom($this->string('provider')->toString());
    }

    /** Returns the inclusive lower calendar-day boundary. */
    public function dateFrom(): ?CarbonImmutable
    {
        $date = $this->string('date_from')->toString();

        if ($date === '') {
            return null;
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }

    /** Returns the inclusive upper calendar-day boundary. */
    public function dateTo(): ?CarbonImmutable
    {
        $date = $this->string('date_to')->toString();

        if ($date === '') {
            return null;
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->endOfDay();
    }
}
