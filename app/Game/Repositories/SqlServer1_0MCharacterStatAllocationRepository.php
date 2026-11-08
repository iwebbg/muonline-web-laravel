<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterStatAllocationRepository;
use App\Game\Data\AccountCharacter;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Reset\ResetProfileSettings;
use App\Stats\Actions\PreviewCharacterStatAllocation;
use App\Stats\CharacterStatAllocationResult;
use App\Stats\Exceptions\CharacterStatAllocationRejected;
use App\Stats\StatAllocation;
use App\Stats\StatAllocationBlockReason;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Applies guarded stat allocations to the verified MuOnline 1.0M schema.
 */
final readonly class SqlServer1_0MCharacterStatAllocationRepository implements CharacterStatAllocationRepository
{
    /**
     * Creates the repository with its transaction manager and preview calculator.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterStatAllocation $previewCharacterStatAllocation,
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Rechecks ownership, offline state, points and limits before one atomic write.
     */
    public function allocate(
        string $accountId,
        string $characterName,
        StatAllocation $allocation,
        ResetProfileSettings $settings,
    ): CharacterStatAllocationResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $allocation,
            $settings,
        ): CharacterStatAllocationResult {
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
                ->first($this->characterColumns());

            if (! $characterRow instanceof stdClass) {
                throw new CharacterStatAllocationRejected(
                    reason: StatAllocationBlockReason::CharacterNotFound,
                    httpStatus: 404,
                );
            }

            $character = $this->mapCharacter(
                row: $characterRow,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewCharacterStatAllocation->handle(
                character: $character,
                allocation: $allocation,
                settings: $settings,
            );

            if (! $preview->eligible) {
                throw new CharacterStatAllocationRejected(
                    $preview->blockReasons[0],
                );
            }

            $updates = [
                'LevelUpPoint' => $preview->remainingPoints,
            ];
            $resultingStats = $preview->resultingStats->toArray();

            foreach ($this->versionProfile->statsForClass($character->classCode) as $statDefinition) {
                $updates[$statDefinition->databaseColumn] = $resultingStats[$statDefinition->key];
            }

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update($updates);

            if ($updatedRows !== 1) {
                throw new CharacterStatAllocationRejected(
                    StatAllocationBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterStatAllocationResult(
                characterName: $characterName,
                beforeStats: $preview->currentStats,
                afterStats: $preview->resultingStats,
                beforePoints: $preview->currentPoints,
                afterPoints: $preview->remainingPoints,
                allocation: $allocation,
            );
        }, 3);
    }

    /**
     * Lists the verified legacy fields required by allocation checks and audit.
     *
     * @return list<string>
     */
    private function characterColumns(): array
    {
        $columns = [
            'Name',
            'cLevel',
            'LevelUpPoint',
            'Class',
            'Money',
            'MapNumber',
            'Resets',
            'GrandResets',
        ];

        foreach ($this->versionProfile->stats() as $statDefinition) {
            $columns[] = $statDefinition->databaseColumn;
        }

        return array_values(array_unique($columns));
    }

    /**
     * Maps the locked legacy row to the portable allocation preview boundary.
     */
    private function mapCharacter(stdClass $row, bool $online): AccountCharacter
    {
        $classCode = (int) $row->Class;
        $stats = [];

        foreach ($this->versionProfile->statsForClass($classCode) as $statDefinition) {
            $databaseColumn = $statDefinition->databaseColumn;
            $stats[$statDefinition->key] = (int) $row->{$databaseColumn};
        }

        $additionalStats = array_diff_key(
            $stats,
            array_flip(['strength', 'agility', 'vitality', 'energy']),
        );

        return new AccountCharacter(
            name: (string) $row->Name,
            className: '',
            classCode: $classCode,
            image: '',
            level: (int) $row->cLevel,
            resets: (int) $row->Resets,
            grandResets: (int) $row->GrandResets,
            zen: (int) $row->Money,
            points: (int) $row->LevelUpPoint,
            strength: $stats['strength'] ?? 0,
            agility: $stats['agility'] ?? 0,
            vitality: $stats['vitality'] ?? 0,
            energy: $stats['energy'] ?? 0,
            map: '',
            online: $online,
            additionalStats: $additionalStats,
        );
    }
}
