<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ModerationRepository;
use App\Game\GameAccountWriteStatus;
use App\Moderation\Exceptions\ModerationRejected;
use App\Moderation\ModerationAction;
use App\Moderation\ModerationBlockReason;
use App\Moderation\ModerationResult;
use App\Moderation\ModerationTarget;
use App\Moderation\ModerationTargetType;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads and updates moderation fields in the verified DarksTeam 97d+99i schema.
 */
final readonly class SqlServer97d99iModerationRepository implements ModerationRepository
{
    /**
     * Creates the repository with the game database transaction manager.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Finds account identifiers and their current login-block state.
     *
     * @return list<ModerationTarget>
     */
    public function searchAccounts(?string $search, int $limit, int $offset = 0): array
    {
        return $this->accountTargets(
            search: $search,
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * Finds account groups by account identifier or one of their character names.
     *
     * @return list<ModerationTarget>
     */
    public function searchAccountGroups(?string $search, int $limit, int $offset = 0): array
    {
        $characterAccountIds = [];

        if ($search !== null) {
            $characterAccountIds = $this->database
                ->connection('game')
                ->table('Character')
                ->where('Name', 'like', '%'.$search.'%')
                ->distinct()
                ->pluck('AccountID')
                ->map(static fn (mixed $accountId): string => (string) $accountId)
                ->all();
        }

        return $this->accountTargets(
            search: $search,
            limit: $limit,
            offset: $offset,
            characterAccountIds: $characterAccountIds,
        );
    }

    /**
     * Builds account moderation targets with optional character-search matches.
     *
     * @param  list<string>  $characterAccountIds
     * @return list<ModerationTarget>
     */
    private function accountTargets(
        ?string $search,
        int $limit,
        int $offset,
        array $characterAccountIds = [],
    ): array {
        $query = $this->database
            ->connection('game')
            ->table('MEMB_INFO as accounts')
            ->select([
                'accounts.memb___id as identifier',
                'accounts.bloc_code as control_code',
            ])
            ->orderBy('accounts.memb___id')
            ->offset($offset)
            ->limit($limit);

        if ($search !== null) {
            $searchPattern = '%'.$search.'%';

            $query->where(static function (Builder $query) use ($characterAccountIds, $searchPattern): void {
                $query->where('accounts.memb___id', 'like', $searchPattern);

                if ($characterAccountIds !== []) {
                    $query->orWhereIn('accounts.memb___id', $characterAccountIds);
                }
            });
        }

        $rows = $query->get();
        $accountIds = $rows
            ->map(static fn (stdClass $row): string => (string) $row->identifier)
            ->all();
        $onlineStatuses = $this->onlineStatuses($accountIds);
        $targets = [];

        foreach ($rows as $row) {
            $controlCode = (int) ($row->control_code ?? 0);
            $identifier = (string) $row->identifier;

            $targets[] = new ModerationTarget(
                type: ModerationTargetType::Account,
                identifier: $identifier,
                accountId: $identifier,
                blocked: $controlCode === 1,
                online: $onlineStatuses[$identifier] ?? true,
                controlCode: $controlCode,
            );
        }

        return $targets;
    }

    /**
     * Finds character names and their current control and account states.
     *
     * @return list<ModerationTarget>
     */
    public function searchCharacters(?string $search, int $limit, int $offset = 0): array
    {
        $query = $this->database
            ->connection('game')
            ->table('Character as characters')
            ->select([
                'characters.Name as identifier',
                'characters.AccountID as account_id',
                'characters.CtlCode as control_code',
            ])
            ->orderBy('characters.AccountID')
            ->orderBy('characters.Name')
            ->offset($offset)
            ->limit($limit);

        if ($search !== null) {
            $searchPattern = '%'.$search.'%';

            $query->where(static function (Builder $query) use ($searchPattern): void {
                $query
                    ->where('characters.Name', 'like', $searchPattern)
                    ->orWhere('characters.AccountID', 'like', $searchPattern);
            });
        }

        return $this->characterTargets($query);
    }

    /**
     * Finds all characters belonging to the visible account page.
     *
     * @param  list<string>  $accountIds
     * @return list<ModerationTarget>
     */
    public function charactersForAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $query = $this->database
            ->connection('game')
            ->table('Character as characters')
            ->select([
                'characters.Name as identifier',
                'characters.AccountID as account_id',
                'characters.CtlCode as control_code',
            ])
            ->whereIn('characters.AccountID', $accountIds)
            ->orderBy('characters.AccountID')
            ->orderBy('characters.Name');

        return $this->characterTargets($query);
    }

    /**
     * Converts one verified character query to credential-free moderation targets.
     *
     * @return list<ModerationTarget>
     */
    private function characterTargets(Builder $query): array
    {
        $rows = $query->get();
        $accountIds = $rows
            ->map(static fn (stdClass $row): string => (string) $row->account_id)
            ->unique()
            ->values()
            ->all();
        $onlineStatuses = $this->onlineStatuses($accountIds);
        $targets = [];

        foreach ($rows as $row) {
            $controlCode = (int) ($row->control_code ?? 0);
            $accountId = (string) $row->account_id;

            $targets[] = new ModerationTarget(
                type: ModerationTargetType::Character,
                identifier: (string) $row->identifier,
                accountId: $accountId,
                blocked: $controlCode === 1,
                online: $onlineStatuses[$accountId] ?? true,
                controlCode: $controlCode,
            );
        }

        return $targets;
    }

    /**
     * Reads connectivity separately to avoid known DarksTeam cross-table collation conflicts.
     *
     * @param  list<string>  $accountIds
     * @return array<string, bool>
     */
    private function onlineStatuses(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = $this->database
            ->connection('game')
            ->table('MEMB_STAT')
            ->whereIn('memb___id', $accountIds)
            ->get(['memb___id', 'ConnectStat']);
        $statuses = [];

        foreach ($rows as $row) {
            $statuses[(string) $row->memb___id] = GameAccountWriteStatus::blocksWrites(
                $row->ConnectStat ?? null,
            );
        }

        return $statuses;
    }

    /**
     * Locks and updates one MEMB_INFO login block without exposing credentials.
     */
    public function moderateAccount(
        string $accountId,
        ModerationAction $action,
    ): ModerationResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $action,
        ): ModerationResult {
            $account = $connection
                ->table('MEMB_INFO')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->first(['memb___id', 'bloc_code']);

            if (! $account instanceof stdClass) {
                throw new ModerationRejected(
                    ModerationBlockReason::TargetNotFound,
                    404,
                );
            }

            $beforeControlCode = (int) ($account->bloc_code ?? 0);

            if (! in_array($beforeControlCode, [0, 1], true)) {
                throw new ModerationRejected(
                    ModerationBlockReason::UnsupportedControlCode,
                    409,
                );
            }

            $afterControlCode = $action->blockedState() ? 1 : 0;

            if ($beforeControlCode !== $afterControlCode) {
                $this->updateAccountControlCode(
                    connection: $connection,
                    accountId: $accountId,
                    controlCode: $afterControlCode,
                );
            }

            return new ModerationResult(
                targetType: ModerationTargetType::Account,
                targetIdentifier: $accountId,
                accountId: $accountId,
                beforeBlocked: $beforeControlCode === 1,
                afterBlocked: $afterControlCode === 1,
                beforeControlCode: $beforeControlCode,
                afterControlCode: $afterControlCode,
            );
        }, 3);
    }

    /**
     * Locks and updates one offline character while preserving GM control codes.
     */
    public function moderateCharacter(
        string $characterName,
        ModerationAction $action,
    ): ModerationResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $characterName,
            $action,
        ): ModerationResult {
            $character = $connection
                ->table('Character')
                ->where('Name', $characterName)
                ->lockForUpdate()
                ->first(['Name', 'AccountID', 'CtlCode']);

            if (! $character instanceof stdClass) {
                throw new ModerationRejected(
                    ModerationBlockReason::TargetNotFound,
                    404,
                );
            }

            $accountId = (string) $character->AccountID;
            $connectStatus = $connection
                ->table('MEMB_STAT')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->value('ConnectStat');

            if (GameAccountWriteStatus::blocksWrites($connectStatus)) {
                throw new ModerationRejected(ModerationBlockReason::TargetOnline);
            }

            $beforeControlCode = (int) ($character->CtlCode ?? 0);

            if (! in_array($beforeControlCode, [0, 1], true)) {
                throw new ModerationRejected(
                    ModerationBlockReason::UnsupportedControlCode,
                    409,
                );
            }

            $afterControlCode = $action->blockedState() ? 1 : 0;

            if ($beforeControlCode !== $afterControlCode) {
                $this->updateCharacterControlCode(
                    connection: $connection,
                    characterName: $characterName,
                    controlCode: $afterControlCode,
                );
            }

            return new ModerationResult(
                targetType: ModerationTargetType::Character,
                targetIdentifier: $characterName,
                accountId: $accountId,
                beforeBlocked: $beforeControlCode === 1,
                afterBlocked: $afterControlCode === 1,
                beforeControlCode: $beforeControlCode,
                afterControlCode: $afterControlCode,
            );
        }, 3);
    }

    /**
     * Persists one account control code and verifies the targeted row count.
     */
    private function updateAccountControlCode(
        ConnectionInterface $connection,
        string $accountId,
        int $controlCode,
    ): void {
        $updatedRows = $connection
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->update(['bloc_code' => $controlCode]);

        if ($updatedRows !== 1) {
            throw new ModerationRejected(
                ModerationBlockReason::OperationInProgress,
                409,
            );
        }
    }

    /**
     * Persists one character control code and verifies the targeted row count.
     */
    private function updateCharacterControlCode(
        ConnectionInterface $connection,
        string $characterName,
        int $controlCode,
    ): void {
        $updatedRows = $connection
            ->table('Character')
            ->where('Name', $characterName)
            ->update(['CtlCode' => $controlCode]);

        if ($updatedRows !== 1) {
            throw new ModerationRejected(
                ModerationBlockReason::OperationInProgress,
                409,
            );
        }
    }
}
