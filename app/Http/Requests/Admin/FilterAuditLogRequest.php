<?php

namespace App\Http\Requests\Admin;

use App\Admin\Audit\AuditLogReader;
use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorizes and normalizes administrator audit-log filters.
 */
final class FilterAuditLogRequest extends FormRequest
{
    /**
     * Allows administrators with audit-log access to inspect immutable history.
     */
    public function authorize(): bool
    {
        $administrator = $this->user('admin');

        return $administrator instanceof Administrator
            && $administrator->hasPermission(AdministratorPermission::ViewAuditLog);
    }

    /**
     * Returns allow-listed filters and inclusive calendar date boundaries.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(AuditLogReader $auditLogReader): array
    {
        return [
            'search' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in($auditLogReader->supportedTypes())],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'failed'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Returns the trimmed subject or character search phrase.
     */
    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    /**
     * Returns the selected audit type or null for every supported type.
     */
    public function type(): ?string
    {
        $type = $this->string('type')->toString();

        return $type === '' ? null : $type;
    }

    /**
     * Returns the selected normalized status or null for every status.
     */
    public function status(): ?string
    {
        $status = $this->string('status')->toString();

        return $status === '' ? null : $status;
    }

    /**
     * Returns the inclusive lower boundary, defaulting to the last thirty days.
     */
    public function dateFrom(): CarbonImmutable
    {
        $date = $this->string('date_from')->toString();

        if ($date === '') {
            return CarbonImmutable::now(config('app.timezone'))
                ->subDays(29)
                ->startOfDay();
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
    }

    /**
     * Returns the inclusive upper boundary, defaulting to the current day.
     */
    public function dateTo(): CarbonImmutable
    {
        $date = $this->string('date_to')->toString();

        if ($date === '') {
            return CarbonImmutable::now(config('app.timezone'))->endOfDay();
        }

        return CarbonImmutable::parse($date, config('app.timezone'))->endOfDay();
    }
}
