<?php

namespace App\Game\Repositories;

use App\CharacterMove\Actions\PreviewMoveToTown;
use App\CharacterMove\CharacterLocationState;
use App\CharacterMove\CharacterMoveBlockReason;
use App\CharacterMove\CharacterMoveResult;
use App\CharacterMove\Exceptions\CharacterMoveRejected;
use App\Game\Contracts\CharacterMoveRepository;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Version97d99i\MapNameMapper;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Reads and updates character locations in the verified MuOnline 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterMoveRepository implements CharacterMoveRepository
{
    /**
     * Creates the repository with its transaction and preview dependencies.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewMoveToTown $previewMoveToTown,
        private MapNameMapper $mapNameMapper,
    ) {}

    /**
     * Returns one ownership-scoped location snapshot without changing game data.
     */
    public function characterFor(
        string $accountId,
        string $characterName,
    ): ?CharacterLocationState {
        $row = $this->characterQuery($accountId, $characterName)
            ->first($this->aliasedCharacterColumns());

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapAliasedCharacter($row);
    }

    /**
     * Rechecks ownership, offline state and Zen before one atomic location update.
     */
    public function moveToTown(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterMoveResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $settings,
        ): CharacterMoveResult {
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
                    'Money',
                    'MapNumber',
                    'MapPosX',
                    'MapPosY',
                ]);

            if (! $characterRow instanceof stdClass) {
                throw new CharacterMoveRejected(
                    CharacterMoveBlockReason::CharacterNotFound,
                    404,
                );
            }

            $character = new CharacterLocationState(
                name: (string) $characterRow->Name,
                map: (int) $characterRow->MapNumber,
                x: (int) $characterRow->MapPosX,
                y: (int) $characterRow->MapPosY,
                zen: (int) $characterRow->Money,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewMoveToTown->handle($character, $settings);

            if (! $preview->eligible) {
                throw new CharacterMoveRejected($preview->blockReasons[0]);
            }

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update([
                    'Money' => $preview->resultingZen,
                    'MapNumber' => $preview->destinationMap,
                    'MapPosX' => $preview->destinationX,
                    'MapPosY' => $preview->destinationY,
                ]);

            if ($updatedRows !== 1) {
                throw new CharacterMoveRejected(
                    CharacterMoveBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterMoveResult(
                characterName: $characterName,
                beforeMap: $preview->currentMap,
                beforeX: $preview->currentX,
                beforeY: $preview->currentY,
                beforeZen: $preview->currentZen,
                afterMap: $preview->destinationMap,
                afterX: $preview->destinationX,
                afterY: $preview->destinationY,
                afterZen: $preview->resultingZen,
                afterMapName: $this->mapNameMapper->nameFor($preview->destinationMap),
            );
        }, 3);
    }

    /**
     * Builds the ownership-scoped location query for preview requests.
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
     * Lists the legacy location fields selected by the read-only preview query.
     *
     * @return list<string>
     */
    private function aliasedCharacterColumns(): array
    {
        return [
            'characters.Name as name',
            'characters.Money as zen',
            'characters.MapNumber as map_number',
            'characters.MapPosX as map_x',
            'characters.MapPosY as map_y',
            'account_status.ConnectStat as connect_stat',
        ];
    }

    /**
     * Maps one aliased legacy row to the location-specific domain snapshot.
     */
    private function mapAliasedCharacter(stdClass $row): CharacterLocationState
    {
        return new CharacterLocationState(
            name: (string) $row->name,
            map: (int) $row->map_number,
            x: (int) $row->map_x,
            y: (int) $row->map_y,
            zen: (int) $row->zen,
            online: GameAccountWriteStatus::blocksWrites($row->connect_stat ?? null),
        );
    }
}
