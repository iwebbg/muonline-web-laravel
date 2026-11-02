<?php

namespace App\Admin\Audit;

use DateTimeImmutable;

/**
 * Carries one normalized audit event from any application-owned operation log.
 */
final readonly class AuditLogEntry
{
    /**
     * Creates one immutable audit entry without exposing credentials or secrets.
     *
     * @param  array<string, mixed>  $contextSnapshot
     * @param  array<string, mixed>|null  $beforeSnapshot
     * @param  array<string, mixed>|null  $afterSnapshot
     */
    public function __construct(
        public string $type,
        public string $subject,
        public ?string $characterName,
        public string $status,
        public ?string $failureCode,
        public array $contextSnapshot,
        public ?array $beforeSnapshot,
        public ?array $afterSnapshot,
        public DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Converts the audit event into the escaped presentation boundary shape.
     *
     * @return array{type: string, subject: string, characterName: string|null, status: string, failureCode: string|null, contextSnapshot: array<string, mixed>, beforeSnapshot: array<string, mixed>|null, afterSnapshot: array<string, mixed>|null, occurredAt: string, occurredAtDisplay: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'subject' => $this->subject,
            'characterName' => $this->characterName,
            'status' => $this->status,
            'failureCode' => $this->failureCode,
            'contextSnapshot' => $this->contextSnapshot,
            'beforeSnapshot' => $this->beforeSnapshot,
            'afterSnapshot' => $this->afterSnapshot,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'occurredAtDisplay' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}
