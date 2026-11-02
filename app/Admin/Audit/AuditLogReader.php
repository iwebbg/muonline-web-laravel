<?php

namespace App\Admin\Audit;

use App\Models\AccountCredentialOperation;
use App\Models\AccountRegistrationOperation;
use App\Models\AdministratorIdentityRevision;
use App\Models\CharacterGrandResetOperation;
use App\Models\CharacterMoveOperation;
use App\Models\CharacterPkClearOperation;
use App\Models\CharacterResetOperation;
use App\Models\CharacterStatAllocationOperation;
use App\Models\CharacterStatResetOperation;
use App\Models\ModerationOperation;
use App\Models\ResetProfileRevision;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Reads application-owned audit tables through one normalized administrator view.
 */
final class AuditLogReader
{
    /**
     * Maps each operation model to its stable audit type and context columns.
     *
     * @var list<array{model: class-string<Model>, type: string, context: list<string>}>
     */
    private const OPERATION_SOURCES = [
        [
            'model' => AccountRegistrationOperation::class,
            'type' => 'account_registration',
            'context' => ['settings_snapshot'],
        ],
        [
            'model' => AccountCredentialOperation::class,
            'type' => 'account_credentials',
            'context' => ['settings_snapshot'],
        ],
        [
            'model' => CharacterResetOperation::class,
            'type' => 'character_reset',
            'context' => ['settings_snapshot'],
        ],
        [
            'model' => CharacterGrandResetOperation::class,
            'type' => 'grand_reset',
            'context' => ['settings_snapshot'],
        ],
        [
            'model' => CharacterStatAllocationOperation::class,
            'type' => 'stat_allocation',
            'context' => ['allocation_snapshot', 'rule_snapshot'],
        ],
        [
            'model' => CharacterStatResetOperation::class,
            'type' => 'stat_reset',
            'context' => ['rule_snapshot'],
        ],
        [
            'model' => CharacterPkClearOperation::class,
            'type' => 'pk_clear',
            'context' => ['rule_snapshot'],
        ],
        [
            'model' => CharacterMoveOperation::class,
            'type' => 'move_to_town',
            'context' => ['rule_snapshot'],
        ],
        [
            'model' => ModerationOperation::class,
            'type' => 'moderation',
            'context' => ['context_snapshot'],
        ],
    ];

    /**
     * Returns the supported allow-listed audit type filters.
     *
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        $types = [
            'reset_profile',
            'game_master_identity',
        ];

        foreach (self::OPERATION_SOURCES as $source) {
            $types[] = $source['type'];
        }

        return $types;
    }

    /**
     * Returns the newest normalized events matching safe administrator filters.
     *
     * @return list<array{type: string, subject: string, characterName: string|null, status: string, failureCode: string|null, contextSnapshot: array<string, mixed>, beforeSnapshot: array<string, mixed>|null, afterSnapshot: array<string, mixed>|null, occurredAt: string, occurredAtDisplay: string}>
     */
    public function latest(
        int $limit = 100,
        ?string $type = null,
        ?string $status = null,
        ?string $search = null,
    ): array {
        $entries = [];

        foreach (self::OPERATION_SOURCES as $source) {
            if ($type !== null && $source['type'] !== $type) {
                continue;
            }

            $records = $source['model']::query()
                ->latest()
                ->limit($limit)
                ->get();

            foreach ($records as $record) {
                $entries[] = $this->operationEntry(
                    record: $record,
                    type: $source['type'],
                    contextColumns: $source['context'],
                );
            }
        }

        if ($type === null || $type === 'reset_profile') {
            $revisions = ResetProfileRevision::query()
                ->latest()
                ->limit($limit)
                ->get();

            foreach ($revisions as $revision) {
                $entries[] = $this->revisionEntry($revision);
            }
        }

        if ($type === null || $type === 'game_master_identity') {
            $identityRevisions = AdministratorIdentityRevision::query()
                ->latest()
                ->limit($limit)
                ->get();

            foreach ($identityRevisions as $identityRevision) {
                $entries[] = $this->identityRevisionEntry($identityRevision);
            }
        }

        $filteredEntries = array_filter(
            $entries,
            fn (AuditLogEntry $entry): bool => $this->matchesFilters(
                entry: $entry,
                status: $status,
                search: $search,
            ),
        );

        usort(
            $filteredEntries,
            fn (AuditLogEntry $left, AuditLogEntry $right): int => $right->occurredAt <=> $left->occurredAt,
        );

        $latestEntries = array_slice($filteredEntries, 0, $limit);
        $results = [];

        foreach ($latestEntries as $entry) {
            $results[] = $entry->toArray();
        }

        return $results;
    }

    /**
     * Normalizes one character or account operation model.
     *
     * @param  list<string>  $contextColumns
     */
    private function operationEntry(Model $record, string $type, array $contextColumns): AuditLogEntry
    {
        $statusAttribute = $record->getAttribute('status');
        $status = $statusAttribute instanceof BackedEnum
            ? (string) $statusAttribute->value
            : (string) $statusAttribute;
        $characterName = $record->getAttribute('character_name');
        $failureCode = $record->getAttribute('failure_code');
        $accountId = $record->getAttribute('account_id');
        $targetIdentifier = $record->getAttribute('target_identifier');
        $subject = is_string($accountId) && $accountId !== ''
            ? $accountId
            : (string) $targetIdentifier;

        return new AuditLogEntry(
            type: $type,
            subject: $subject,
            characterName: is_string($characterName) ? $characterName : null,
            status: $status,
            failureCode: is_string($failureCode) ? $failureCode : null,
            contextSnapshot: $this->contextSnapshot($record, $contextColumns),
            beforeSnapshot: $this->nullableSnapshot($record, 'before_snapshot'),
            afterSnapshot: $this->nullableSnapshot($record, 'after_snapshot'),
            occurredAt: $this->occurredAt($record),
        );
    }

    /**
     * Normalizes one reset policy revision as a completed administrator event.
     */
    private function revisionEntry(ResetProfileRevision $revision): AuditLogEntry
    {
        $administratorId = $revision->changed_by_administrator_id;
        $subject = $administratorId === null
            ? 'system'
            : 'admin:'.$administratorId;

        return new AuditLogEntry(
            type: 'reset_profile',
            subject: $subject,
            characterName: null,
            status: 'completed',
            failureCode: null,
            contextSnapshot: [
                'revision' => $revision->revision,
            ],
            beforeSnapshot: $revision->before_snapshot,
            afterSnapshot: $revision->after_snapshot,
            occurredAt: $this->occurredAt($revision),
        );
    }

    /**
     * Normalizes one password-free Game Master identity revision.
     */
    private function identityRevisionEntry(
        AdministratorIdentityRevision $revision,
    ): AuditLogEntry {
        $targetAdministratorId = $revision->target_administrator_id;
        $subject = $targetAdministratorId === null
            ? 'gm:deleted'
            : 'gm:'.$targetAdministratorId;

        return new AuditLogEntry(
            type: 'game_master_identity',
            subject: $subject,
            characterName: null,
            status: 'completed',
            failureCode: null,
            contextSnapshot: [
                'action' => $revision->action,
                'changed_by_administrator_id' => $revision->changed_by_administrator_id,
            ],
            beforeSnapshot: $revision->before_snapshot,
            afterSnapshot: $revision->after_snapshot,
            occurredAt: $this->occurredAt($revision),
        );
    }

    /**
     * Collects named operation context snapshots without flattening their meaning.
     *
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function contextSnapshot(Model $record, array $columns): array
    {
        $context = [];

        foreach ($columns as $column) {
            $snapshot = $record->getAttribute($column);

            if (is_array($snapshot)) {
                $context[$column] = $snapshot;
            }
        }

        return $context;
    }

    /**
     * Returns one nullable structured audit snapshot.
     *
     * @return array<string, mixed>|null
     */
    private function nullableSnapshot(Model $record, string $column): ?array
    {
        $snapshot = $record->getAttribute($column);

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Returns the immutable creation time required for cross-table sorting.
     */
    private function occurredAt(Model $record): DateTimeImmutable
    {
        $createdAt = $record->getAttribute('created_at');

        if (! $createdAt instanceof DateTimeInterface) {
            throw new LogicException('Audit records must expose a valid creation timestamp.');
        }

        return DateTimeImmutable::createFromInterface($createdAt);
    }

    /**
     * Applies status and free-text filters after normalizing heterogeneous tables.
     */
    private function matchesFilters(
        AuditLogEntry $entry,
        ?string $status,
        ?string $search,
    ): bool {
        if ($status !== null && $entry->status !== $status) {
            return false;
        }

        if ($search === null || $search === '') {
            return true;
        }

        $searchableText = implode(' ', array_filter([
            $entry->type,
            $entry->subject,
            $entry->characterName,
            $entry->failureCode,
        ]));

        return Str::contains(
            Str::lower($searchableText),
            Str::lower($search),
        );
    }
}
