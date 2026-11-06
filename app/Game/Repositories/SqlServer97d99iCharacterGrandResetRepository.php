<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterGrandResetRepository;
use App\Game\Data\AccountCharacter;
use App\Game\GameAccountWriteStatus;
use App\GrandReset\Actions\PreviewCharacterGrandReset;
use App\GrandReset\CharacterGrandResetResult;
use App\GrandReset\Exceptions\CharacterGrandResetRejected;
use App\GrandReset\GrandResetBlockReason;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Applies guarded grand resets to the verified MuOnline 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterGrandResetRepository implements CharacterGrandResetRepository
{
    /**
     * Creates the repository with its transaction manager and preview calculator.
     */
    public function __construct(
        private DatabaseManager $database,
        private PreviewCharacterGrandReset $previewCharacterGrandReset,
    ) {}

    /**
     * Rechecks every mutable requirement before one atomic grand-reset write.
     */
    public function grandReset(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterGrandResetResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $settings,
        ): CharacterGrandResetResult {
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
                throw new CharacterGrandResetRejected(
                    reason: GrandResetBlockReason::CharacterNotFound,
                    httpStatus: 404,
                );
            }

            $character = $this->mapCharacter(
                row: $characterRow,
                online: GameAccountWriteStatus::blocksWrites($connectStatus),
            );
            $preview = $this->previewCharacterGrandReset->handle(
                character: $character,
                settings: $settings,
            );

            if (! $preview->eligible) {
                throw new CharacterGrandResetRejected(
                    $preview->blockReasons[0],
                );
            }

            $updatedRows = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update([
                    'cLevel' => $preview->resultingLevel,
                    'Experience' => 0,
                    'Resets' => $preview->resultingResets,
                    'GrandResets' => $preview->resultingGrandResets,
                    'Money' => $preview->resultingZen,
                    'LevelUpPoint' => $preview->resultingPoints,
                    'Strength' => $preview->resultingStats->strength,
                    'Dexterity' => $preview->resultingStats->agility,
                    'Vitality' => $preview->resultingStats->vitality,
                    'Energy' => $preview->resultingStats->energy,
                ]);

            if ($updatedRows !== 1) {
                throw new CharacterGrandResetRejected(
                    GrandResetBlockReason::OperationInProgress,
                    409,
                );
            }

            return new CharacterGrandResetResult(
                characterName: $characterName,
                beforeLevel: $preview->currentLevel,
                afterLevel: $preview->resultingLevel,
                beforeResets: $preview->currentResets,
                afterResets: $preview->resultingResets,
                beforeGrandResets: $preview->currentGrandResets,
                afterGrandResets: $preview->resultingGrandResets,
                beforeZen: $preview->currentZen,
                afterZen: $preview->resultingZen,
                beforePoints: $preview->currentPoints,
                afterPoints: $preview->resultingPoints,
                beforeStats: $preview->currentStats,
                afterStats: $preview->resultingStats,
            );
        }, 3);
    }

    /**
     * Lists verified legacy fields required by grand-reset checks and audit.
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
     * Maps the locked legacy row to the portable grand-reset preview boundary.
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
