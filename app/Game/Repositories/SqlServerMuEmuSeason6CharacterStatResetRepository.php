<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Data\AccountCharacter;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Reset\ResetProfileSettings;
use App\StatReset\Actions\PreviewCharacterStatReset;
use App\StatReset\CharacterStatResetResult;
use App\StatReset\Exceptions\CharacterStatResetRejected;
use App\StatReset\StatResetBlockReason;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Applies guarded stat resets to the verified MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6CharacterStatResetRepository implements CharacterStatResetRepository
{
    /**
     * Creates the repository with its transaction manager and preview calculator.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterStatReset $previewCharacterStatReset,
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Rechecks ownership, offline state and rules before one atomic stat reset.
     */
    public function resetStats(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterStatResetResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $settings,
        ): CharacterStatResetResult {
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
                throw new CharacterStatResetRejected(
                    StatResetBlockReason::CharacterNotFound,
                    404,
                );
            }

            $character = $this->mapCharacter(
                row: $characterRow,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewCharacterStatReset->handle(
                character: $character,
                settings: $settings,
            );

            if (! $preview->eligible) {
                throw new CharacterStatResetRejected(
                    $preview->blockReasons[0],
                );
            }

            $updates = [
                'LevelUpPoint' => $preview->resultingPoints,
                'Money' => $preview->resultingZen,
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
                throw new CharacterStatResetRejected(
                    StatResetBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterStatResetResult(
                characterName: $characterName,
                beforeZen: $preview->currentZen,
                afterZen: $preview->resultingZen,
                beforePoints: $preview->currentPoints,
                refundedPoints: $preview->refundedPoints,
                afterPoints: $preview->resultingPoints,
                beforeStats: $preview->currentStats,
                afterStats: $preview->resultingStats,
            );
        }, 3);
    }

    /**
     * Lists verified legacy fields required by stat-reset checks and audit.
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
            'ResetCount',
            'MasterResetCount',
        ];

        foreach ($this->versionProfile->stats() as $statDefinition) {
            $columns[] = $statDefinition->databaseColumn;
        }

        return array_values(array_unique($columns));
    }

    /**
     * Maps the locked legacy row to the portable stat-reset preview boundary.
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
            resets: (int) $row->ResetCount,
            grandResets: (int) $row->MasterResetCount,
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
