<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterResetRepository;
use App\Game\Data\AccountCharacter;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Reset\Actions\PreviewCharacterReset;
use App\Reset\CharacterResetResult;
use App\Reset\Exceptions\CharacterResetRejected;
use App\Reset\InventoryPolicy;
use App\Reset\ResetBlockReason;
use App\Reset\ResetProfileSettings;
use App\Stats\CharacterStats;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use stdClass;

/**
 * Applies guarded character resets to the verified MuEMU Season 6 schema.
 */
final readonly class SqlServerMuEmuSeason6CharacterResetRepository implements CharacterResetRepository
{
    /**
     * Creates the repository with its transaction manager and version adapter.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterReset $previewCharacterReset,
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Rechecks ownership, online state and resources before one atomic game write.
     */
    public function reset(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterResetResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $settings,
        ): CharacterResetResult {
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
                throw new CharacterResetRejected(
                    reason: ResetBlockReason::CharacterNotFound,
                    httpStatus: 404,
                );
            }

            $character = $this->mapCharacter(
                row: $characterRow,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewCharacterReset->handle(
                character: $character,
                settings: $settings,
            );

            if (! $preview->eligible) {
                throw new CharacterResetRejected($preview->blockReasons[0]);
            }

            if ($settings->inventoryPolicy !== InventoryPolicy::KeepAll) {
                throw new CharacterResetRejected(
                    ResetBlockReason::InventoryPolicyUnsupported,
                );
            }

            $baseStats = $this->baseStatsFor(
                classCode: $character->classCode,
                clearStats: $settings->clearStats,
            );
            $beforeSnapshot = $this->snapshotFromCharacter($character);
            $afterSnapshot = $this->afterSnapshot(
                character: $character,
                resultingResets: $preview->resultingResets,
                resultingZen: $preview->resultingZen,
                resultingPoints: $preview->resultingPoints,
                baseStats: $baseStats,
            );
            $updates = [
                'cLevel' => $afterSnapshot['level'],
                'Experience' => 0,
                'LevelUpPoint' => $afterSnapshot['points'],
                'Money' => $afterSnapshot['zen'],
                'ResetCount' => $afterSnapshot['resets'],
            ];

            if ($baseStats !== null) {
                foreach ($this->versionProfile->statsForClass($character->classCode) as $statDefinition) {
                    $updates[$statDefinition->databaseColumn] = $baseStats->toArray()[$statDefinition->key];
                }
            }

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update($updates);

            if ($updatedRows !== 1) {
                throw new CharacterResetRejected(
                    ResetBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterResetResult(
                characterName: $characterName,
                beforeSnapshot: $beforeSnapshot,
                afterSnapshot: $afterSnapshot,
                zenCost: $preview->zenCost,
                statsCleared: $settings->clearStats,
                inventoryPolicy: $settings->inventoryPolicy,
            );
        }, 3);
    }

    /**
     * Lists the verified legacy fields required by reset checks and audit.
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
            'ResetCount',
            'MasterResetCount',
        ];

        foreach ($this->versionProfile->stats() as $statDefinition) {
            $columns[] = $statDefinition->databaseColumn;
        }

        return array_values(array_unique($columns));
    }

    /**
     * Maps the locked legacy row to the reset preview boundary.
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

    /**
     * Resolves class defaults only when the active policy clears attributes.
     */
    private function baseStatsFor(
        int $classCode,
        bool $clearStats,
    ): ?CharacterStats {
        if (! $clearStats) {
            return null;
        }

        try {
            return $this->versionProfile->baseStatsForClass($classCode);
        } catch (InvalidArgumentException) {
            throw new CharacterResetRejected(
                ResetBlockReason::CharacterClassUnsupported,
            );
        }
    }

    /**
     * Creates the normalized audit snapshot for the locked current state.
     *
     * @return array<string, int>
     */
    private function snapshotFromCharacter(AccountCharacter $character): array
    {
        return [
            'level' => $character->level,
            'resets' => $character->resets,
            'zen' => $character->zen,
            'points' => $character->points,
            ...$character->stats()->toArray(),
        ];
    }

    /**
     * Calculates the exact normalized state that will be persisted.
     *
     * @return array<string, int>
     */
    private function afterSnapshot(
        AccountCharacter $character,
        int $resultingResets,
        int $resultingZen,
        int $resultingPoints,
        ?CharacterStats $baseStats,
    ): array {
        $resultingStats = $baseStats?->toArray()
            ?? $character->stats()->toArray();

        return [
            'level' => 1,
            'resets' => $resultingResets,
            'zen' => $resultingZen,
            'points' => $resultingPoints,
            ...$resultingStats,
        ];
    }
}
