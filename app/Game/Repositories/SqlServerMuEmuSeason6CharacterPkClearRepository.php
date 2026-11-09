<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterPkClearRepository;
use App\Game\GameAccountWriteStatus;
use App\PkClear\Actions\PreviewCharacterPkClear;
use App\PkClear\CharacterPkClearResult;
use App\PkClear\CharacterPkState;
use App\PkClear\Exceptions\CharacterPkClearRejected;
use App\PkClear\PkClearBlockReason;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads and clears PK state in the verified MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6CharacterPkClearRepository implements CharacterPkClearRepository
{
    /**
     * Creates the repository with its transaction manager and preview calculator.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterPkClear $previewCharacterPkClear,
    ) {}

    /**
     * Returns one ownership-scoped PK snapshot without changing game data.
     */
    public function characterFor(
        string $accountId,
        string $characterName,
    ): ?CharacterPkState {
        $row = $this->characterQuery($accountId, $characterName)
            ->first($this->aliasedCharacterColumns());

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapAliasedCharacter($row);
    }

    /**
     * Rechecks ownership, offline state and price before one atomic PK clear.
     */
    public function clearPk(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterPkClearResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $settings,
        ): CharacterPkClearResult {
            $connectStatus = $connection
                ->table('MEMB_STAT')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->value('ConnectStat');
            $characterRow = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->lockForUpdate()
                ->first([
                    'Name',
                    'cLevel',
                    'Money',
                    'PkLevel',
                    'PkCount',
                    'PkTime',
                ]);

            if (! $characterRow instanceof stdClass) {
                throw new CharacterPkClearRejected(
                    PkClearBlockReason::CharacterNotFound,
                    404,
                );
            }

            $character = new CharacterPkState(
                name: (string) $characterRow->Name,
                level: (int) $characterRow->cLevel,
                zen: (int) $characterRow->Money,
                pkLevel: (int) $characterRow->PkLevel,
                pkCount: (int) $characterRow->PkCount,
                pkTime: (int) $characterRow->PkTime,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewCharacterPkClear->handle(
                character: $character,
                settings: $settings,
            );

            if (! $preview->eligible) {
                throw new CharacterPkClearRejected(
                    $preview->blockReasons[0],
                );
            }

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update([
                    'Money' => $preview->resultingZen,
                    'PkLevel' => $preview->resultingPkLevel,
                    'PkCount' => $preview->resultingPkCount,
                    'PkTime' => $preview->resultingPkTime,
                ]);

            if ($updatedRows !== 1) {
                throw new CharacterPkClearRejected(
                    PkClearBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterPkClearResult(
                characterName: $characterName,
                beforeZen: $preview->currentZen,
                afterZen: $preview->resultingZen,
                beforePkLevel: $preview->currentPkLevel,
                afterPkLevel: $preview->resultingPkLevel,
                beforePkCount: $preview->currentPkCount,
                afterPkCount: $preview->resultingPkCount,
                beforePkTime: $preview->currentPkTime,
                afterPkTime: $preview->resultingPkTime,
            );
        }, 3);
    }

    /**
     * Builds the ownership-scoped PK read query for the preview endpoint.
     */
    private function characterQuery(string $accountId, string $characterName): Builder
    {
        return $this->database
            ->connection('game')
            ->table('Character as characters')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->where('characters.AccountID', $accountId)
            ->where('characters.Name', $characterName);
    }

    /**
     * Lists PK fields selected by the read-only preview query.
     *
     * @return list<string>
     */
    private function aliasedCharacterColumns(): array
    {
        return [
            'characters.Name as name',
            'characters.cLevel as level',
            'characters.Money as zen',
            'characters.PkLevel as pk_level',
            'characters.PkCount as pk_count',
            'characters.PkTime as pk_time',
            'account_status.ConnectStat as connect_stat',
        ];
    }

    /**
     * Maps one aliased legacy row to the PK-specific domain snapshot.
     */
    private function mapAliasedCharacter(stdClass $row): CharacterPkState
    {
        return new CharacterPkState(
            name: (string) $row->name,
            level: (int) $row->level,
            zen: (int) $row->zen,
            pkLevel: (int) $row->pk_level,
            pkCount: (int) $row->pk_count,
            pkTime: (int) $row->pk_time,
            online: GameAccountWriteStatus::blocksWrites($row->connect_stat ?? null),
        );
    }
}
