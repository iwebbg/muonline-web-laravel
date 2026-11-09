<?php

namespace App\Game\Repositories;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Contracts\CharacterServiceRepository;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Reset\InventoryPolicy;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use stdClass;

/**
 * Mutates identity fields shared by the verified DarksTeam schemas.
 */
abstract readonly class SqlServerCharacterServiceRepository implements CharacterServiceRepository
{
    private const MAXIMUM_LEVEL_UP_POINTS = 2_147_483_647;

    /**
     * Creates the repository with the isolated game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Renames one owned offline character and all verified core name references atomically.
     */
    public function rename(string $accountId, string $currentName, string $newName): CharacterServiceResult
    {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use ($connection, $accountId, $currentName, $newName): CharacterServiceResult {
            $this->ensureAccountIsOffline($connection, $accountId);
            $current = $this->lockedCharacter($connection, $accountId, $currentName);

            if (! $current instanceof stdClass) {
                $alreadyRenamed = $this->lockedCharacter($connection, $accountId, $newName);

                if ($alreadyRenamed instanceof stdClass) {
                    return $this->renameResult($currentName, $newName, (int) $alreadyRenamed->Class);
                }

                throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
            }

            if (strcasecmp($currentName, $newName) === 0) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::SameName);
            }

            $nameTaken = $connection->table('Character')
                ->where('Name', $newName)
                ->lockForUpdate()
                ->exists();

            if ($nameTaken) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::NameTaken);
            }

            foreach ($this->accountCharacterSlots() as $slot) {
                $connection->table('AccountCharacter')
                    ->where('Id', $accountId)
                    ->where($slot, $currentName)
                    ->update([$slot => $newName]);
            }

            $connection->table('GuildMember')->where('Name', $currentName)->update(['Name' => $newName]);
            $connection->table('Guild')->where('G_Master', $currentName)->update(['G_Master' => $newName]);
            $connection->table('Character')->where('MarryName', $currentName)->update(['MarryName' => $newName]);
            $this->updateProfileNameReferences($connection, $currentName, $newName);

            $updatedRows = $connection->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $currentName)
                ->update(['Name' => $newName]);

            if ($updatedRows !== 1) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress, 409);
            }

            return $this->renameResult($currentName, $newName, (int) $current->Class);
        }, 3);
    }

    /**
     * Changes one owned offline character class atomically.
     */
    public function changeClass(string $accountId, string $characterName, int $targetClassCode): CharacterServiceResult
    {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use ($connection, $accountId, $characterName, $targetClassCode): CharacterServiceResult {
            $this->ensureAccountIsOffline($connection, $accountId);
            $character = $this->lockedCharacter($connection, $accountId, $characterName);

            if (! $character instanceof stdClass) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
            }

            $currentClassCode = (int) $character->Class;

            if ($currentClassCode !== $targetClassCode) {
                $updatedRows = $connection->table('Character')
                    ->where('AccountID', $accountId)
                    ->where('Name', $characterName)
                    ->where('Class', $currentClassCode)
                    ->update(['Class' => $targetClassCode]);

                if ($updatedRows !== 1) {
                    throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress, 409);
                }
            }

            return new CharacterServiceResult(
                before: ['name' => $characterName, 'class_code' => $currentClassCode],
                after: ['name' => $characterName, 'class_code' => $targetClassCode],
            );
        }, 3);
    }

    /**
     * Applies purchased resets using the captured profile rules without Zen or rewards.
     */
    public function purchaseResets(
        string $accountId,
        string $characterName,
        int $resetCount,
        PurchasedResetPolicy $policy,
    ): CharacterServiceResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $resetCount,
            $policy,
        ): CharacterServiceResult {
            $this->ensureAccountIsOffline($connection, $accountId);
            $character = $this->lockedProgressionCharacter($connection, $accountId, $characterName);

            if (! $character instanceof stdClass) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
            }

            if ($resetCount < 1) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidQuantity);
            }

            if ($policy->inventoryPolicy !== InventoryPolicy::KeepAll) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::ResetPolicyUnsupported);
            }

            $resetColumn = $this->resetColumn();
            $resultingResets = (int) $character->{$resetColumn} + $resetCount;

            if ($resultingResets > $policy->maximumResets) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::MaximumResetsReached);
            }

            $resultingPoints = $policy->pointsFor($resultingResets);

            if ($resultingPoints > self::MAXIMUM_LEVEL_UP_POINTS) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::PointLimitExceeded);
            }

            $before = $this->progressionSnapshot($character);
            $updates = [
                'cLevel' => 1,
                'Experience' => 0,
                'LevelUpPoint' => $resultingPoints,
                $resetColumn => $resultingResets,
            ];
            $afterStats = $this->statsFromRow($character);

            if ($policy->clearStats) {
                try {
                    $afterStats = $this->versionProfile
                        ->baseStatsForClass((int) $character->Class)
                        ->toArray();
                } catch (InvalidArgumentException) {
                    throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
                }

                foreach ($this->versionProfile->statsForClass((int) $character->Class) as $statDefinition) {
                    $updates[$statDefinition->databaseColumn] = $afterStats[$statDefinition->key];
                }
            }

            $updatedRows = $connection->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update($updates);

            if ($updatedRows !== 1) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress, 409);
            }

            return new CharacterServiceResult(
                before: $before,
                after: [
                    'level' => 1,
                    'resets' => $resultingResets,
                    'points' => $resultingPoints,
                    'zen' => (int) $character->Money,
                    ...$afterStats,
                ],
            );
        }, 3);
    }

    /**
     * Adds purchased unallocated points after rechecking ownership and offline state.
     */
    public function purchaseLevelUpPoints(
        string $accountId,
        string $characterName,
        int $pointCount,
    ): CharacterServiceResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $pointCount,
        ): CharacterServiceResult {
            $this->ensureAccountIsOffline($connection, $accountId);
            $character = $this->lockedProgressionCharacter($connection, $accountId, $characterName);

            if (! $character instanceof stdClass) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
            }

            if ($pointCount < 1) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidQuantity);
            }

            $resultingPoints = (int) $character->LevelUpPoint + $pointCount;

            if ($resultingPoints > self::MAXIMUM_LEVEL_UP_POINTS) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::PointLimitExceeded);
            }

            $before = $this->progressionSnapshot($character);
            $updatedRows = $connection->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update(['LevelUpPoint' => $resultingPoints]);

            if ($updatedRows !== 1) {
                throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress, 409);
            }

            return new CharacterServiceResult(
                before: $before,
                after: [
                    ...$before,
                    'points' => $resultingPoints,
                ],
            );
        }, 3);
    }

    /**
     * Locks and returns one ownership-scoped character identity.
     */
    private function lockedCharacter(
        ConnectionInterface $connection,
        string $accountId,
        string $characterName,
    ): ?stdClass {
        $character = $connection->table('Character')
            ->where('AccountID', $accountId)
            ->where('Name', $characterName)
            ->lockForUpdate()
            ->first(['Name', 'Class']);

        return $character instanceof stdClass ? $character : null;
    }

    /**
     * Locks and returns the complete profile-aware progression snapshot.
     */
    private function lockedProgressionCharacter(
        ConnectionInterface $connection,
        string $accountId,
        string $characterName,
    ): ?stdClass {
        $columns = [
            'Name',
            'Class',
            'cLevel',
            'LevelUpPoint',
            'Money',
            $this->resetColumn(),
        ];

        foreach ($this->versionProfile->stats() as $statDefinition) {
            $columns[] = $statDefinition->databaseColumn;
        }

        $character = $connection->table('Character')
            ->where('AccountID', $accountId)
            ->where('Name', $characterName)
            ->lockForUpdate()
            ->first(array_values(array_unique($columns)));

        return $character instanceof stdClass ? $character : null;
    }

    /**
     * Converts a locked character row to the normalized progression audit shape.
     *
     * @return array<string, int>
     */
    private function progressionSnapshot(stdClass $character): array
    {
        return [
            'level' => (int) $character->cLevel,
            'resets' => (int) $character->{$this->resetColumn()},
            'points' => (int) $character->LevelUpPoint,
            'zen' => (int) $character->Money,
            ...$this->statsFromRow($character),
        ];
    }

    /**
     * Reads every stat supported by the character's current class.
     *
     * @return array<string, int>
     */
    private function statsFromRow(stdClass $character): array
    {
        $stats = [];

        try {
            $statDefinitions = $this->versionProfile->statsForClass((int) $character->Class);
        } catch (InvalidArgumentException) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
        }

        foreach ($statDefinitions as $statDefinition) {
            $databaseColumn = $statDefinition->databaseColumn;
            $stats[$statDefinition->key] = (int) $character->{$databaseColumn};
        }

        return $stats;
    }

    /**
     * Rejects all character writes while the owning account is connected.
     */
    private function ensureAccountIsOffline(ConnectionInterface $connection, string $accountId): void
    {
        $connectStatus = $connection->table('MEMB_STAT')
            ->where('memb___id', $accountId)
            ->lockForUpdate()
            ->value('ConnectStat');

        if (GameAccountWriteStatus::blocksWrites($connectStatus)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterOnline);
        }
    }

    /**
     * Builds the stable rename audit snapshots.
     */
    private function renameResult(string $currentName, string $newName, int $classCode): CharacterServiceResult
    {
        return new CharacterServiceResult(
            before: ['name' => $currentName, 'class_code' => $classCode],
            after: ['name' => $newName, 'class_code' => $classCode],
        );
    }

    /**
     * Updates verified profile-specific name references before the character key changes.
     */
    protected function updateProfileNameReferences(
        ConnectionInterface $connection,
        string $currentName,
        string $newName,
    ): void {}

    /**
     * Returns character slots referenced by rename operations in the active schema.
     *
     * @return list<string>
     */
    protected function accountCharacterSlots(): array
    {
        return ['GameID1', 'GameID2', 'GameID3', 'GameID4', 'GameID5', 'GameIDC'];
    }

    /**
     * Returns the native reset counter used by purchased reset packages.
     */
    protected function resetColumn(): string
    {
        return 'Resets';
    }
}
