<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterResetRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterBaseStats;
use App\Game\Versions\Version97d99i\CharacterBaseStatsMapper;
use App\Reset\Actions\PreviewCharacterReset;
use App\Reset\CharacterResetResult;
use App\Reset\Exceptions\CharacterResetRejected;
use App\Reset\InventoryPolicy;
use App\Reset\ResetBlockReason;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use stdClass;

/**
 * Applies guarded character resets to the verified MuOnline 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterResetRepository implements CharacterResetRepository
{
    /**
     * Creates the repository with its transaction manager and version adapter.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterReset $previewCharacterReset,
        private CharacterBaseStatsMapper $baseStatsMapper,
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
                online: (int) ($connectStatus ?? 0) === 1,
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
                'Resets' => $afterSnapshot['resets'],
            ];

            if ($baseStats !== null) {
                $updates['Strength'] = $baseStats->strength;
                $updates['Dexterity'] = $baseStats->agility;
                $updates['Vitality'] = $baseStats->vitality;
                $updates['Energy'] = $baseStats->energy;
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
        return [
            'Name',
            'cLevel',
            'LevelUpPoint',
            'Class',
            'Strength',
            'Dexterity',
            'Vitality',
            'Energy',
            'Money',
            'Resets',
            'GrandResets',
        ];
    }

    /**
     * Maps the locked legacy row to the reset preview boundary.
     */
    private function mapCharacter(stdClass $row, bool $online): AccountCharacter
    {
        return new AccountCharacter(
            name: (string) $row->Name,
            className: '',
            classCode: (int) $row->Class,
            image: '',
            level: (int) $row->cLevel,
            resets: (int) $row->Resets,
            grandResets: (int) $row->GrandResets,
            zen: (int) $row->Money,
            points: (int) $row->LevelUpPoint,
            strength: (int) $row->Strength,
            agility: (int) $row->Dexterity,
            vitality: (int) $row->Vitality,
            energy: (int) $row->Energy,
            map: '',
            online: $online,
        );
    }

    /**
     * Resolves class defaults only when the active policy clears attributes.
     */
    private function baseStatsFor(
        int $classCode,
        bool $clearStats,
    ): ?CharacterBaseStats {
        if (! $clearStats) {
            return null;
        }

        try {
            return $this->baseStatsMapper->forClass($classCode);
        } catch (InvalidArgumentException) {
            throw new CharacterResetRejected(
                ResetBlockReason::CharacterClassUnsupported,
            );
        }
    }

    /**
     * Creates the normalized audit snapshot for the locked current state.
     *
     * @return array{level: int, resets: int, zen: int, points: int, strength: int, agility: int, vitality: int, energy: int}
     */
    private function snapshotFromCharacter(AccountCharacter $character): array
    {
        return [
            'level' => $character->level,
            'resets' => $character->resets,
            'zen' => $character->zen,
            'points' => $character->points,
            'strength' => $character->strength,
            'agility' => $character->agility,
            'vitality' => $character->vitality,
            'energy' => $character->energy,
        ];
    }

    /**
     * Calculates the exact normalized state that will be persisted.
     *
     * @return array{level: int, resets: int, zen: int, points: int, strength: int, agility: int, vitality: int, energy: int}
     */
    private function afterSnapshot(
        AccountCharacter $character,
        int $resultingResets,
        int $resultingZen,
        int $resultingPoints,
        ?CharacterBaseStats $baseStats,
    ): array {
        return [
            'level' => 1,
            'resets' => $resultingResets,
            'zen' => $resultingZen,
            'points' => $resultingPoints,
            'strength' => $baseStats?->strength ?? $character->strength,
            'agility' => $baseStats?->agility ?? $character->agility,
            'vitality' => $baseStats?->vitality ?? $character->vitality,
            'energy' => $baseStats?->energy ?? $character->energy,
        ];
    }
}
