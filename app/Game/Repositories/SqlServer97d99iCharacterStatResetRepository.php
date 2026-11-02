<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterStatResetRepository;
use App\Game\Data\AccountCharacter;
use App\Reset\ResetProfileSettings;
use App\StatReset\Actions\PreviewCharacterStatReset;
use App\StatReset\CharacterStatResetResult;
use App\StatReset\Exceptions\CharacterStatResetRejected;
use App\StatReset\StatResetBlockReason;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Applies guarded stat resets to the verified MuOnline 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterStatResetRepository implements CharacterStatResetRepository
{
    /**
     * Creates the repository with its transaction manager and preview calculator.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterStatReset $previewCharacterStatReset,
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
                online: (int) ($connectStatus ?? 0) === 1,
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

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update([
                    'LevelUpPoint' => $preview->resultingPoints,
                    'Money' => $preview->resultingZen,
                    'Strength' => $preview->resultingStats->strength,
                    'Dexterity' => $preview->resultingStats->agility,
                    'Vitality' => $preview->resultingStats->vitality,
                    'Energy' => $preview->resultingStats->energy,
                ]);

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
            'MapNumber',
            'Resets',
            'GrandResets',
        ];
    }

    /**
     * Maps the locked legacy row to the portable stat-reset preview boundary.
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
}
