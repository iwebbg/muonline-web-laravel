<?php

namespace App\Game\Repositories;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Game\Contracts\CharacterServiceRepository;
use App\Game\GameAccountWriteStatus;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Mutates identity fields shared by the verified DarksTeam schemas.
 */
abstract readonly class SqlServerCharacterServiceRepository implements CharacterServiceRepository
{
    /**
     * Creates the repository with the isolated game database manager.
     */
    public function __construct(private DatabaseManager $database) {}

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

            foreach (['GameID1', 'GameID2', 'GameID3', 'GameID4', 'GameID5', 'GameIDC'] as $slot) {
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
}
