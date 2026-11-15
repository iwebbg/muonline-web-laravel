<?php

namespace App\Game\Repositories;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Contracts\JournaledCharacterServiceRepository;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\CharacterServiceJournalMuDevsSeason21;
use App\Reset\InventoryPolicy;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use LogicException;
use stdClass;

/**
 * Applies only journaled S21 reset and point purchases while preserving all dependent game state.
 */
final readonly class SqlServerMuDevsSeason21CharacterServiceRepository implements JournaledCharacterServiceRepository
{
    private const MAXIMUM_COUNTER = 2_147_483_647;

    /**
     * Uses verified native columns, class rules and immutable paid-operation receipts.
     */
    public function __construct(
        private DatabaseManager $database,
        private GameVersionProfile $versionProfile,
        private CharacterServiceJournalMuDevsSeason21 $journal,
    ) {}

    /**
     * Rejects uncommissioned native renaming and its unsynchronized name references.
     */
    public function rename(string $accountId, string $currentName, string $newName): CharacterServiceResult
    {
        throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
    }

    /**
     * Rejects uncommissioned native class and advancement changes.
     */
    public function changeClass(string $accountId, string $characterName, int $targetClassCode): CharacterServiceResult
    {
        throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
    }

    /**
     * Requires callers to retain a durable operation identity before purchasing resets.
     */
    public function purchaseResets(string $accountId, string $characterName, int $resetCount, PurchasedResetPolicy $policy): CharacterServiceResult
    {
        throw new LogicException('Native S21 paid resets require an operation identity.');
    }

    /**
     * Requires callers to retain a durable operation identity before purchasing points.
     */
    public function purchaseLevelUpPoints(string $accountId, string $characterName, int $pointCount): CharacterServiceResult
    {
        throw new LogicException('Native S21 paid points require an operation identity.');
    }

    /**
     * Replays committed results before evaluating current online state or mutable character counters.
     */
    public function applyPurchaseForOperation(
        string $accountId,
        string $characterName,
        CharacterServiceType $type,
        int $amount,
        ?PurchasedResetPolicy $policy,
        string $operationKey,
    ): CharacterServiceResult {
        if (! in_array($type, [CharacterServiceType::ResetPurchase, CharacterServiceType::LevelUpPointsPurchase], true)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ServiceDisabled);
        }

        $hash = hash('sha256', json_encode([
            'account' => $accountId, 'character' => $characterName, 'type' => $type->value,
            'amount' => $amount, 'policy' => $policy?->toSnapshot(),
        ], JSON_THROW_ON_ERROR));
        $connection = $this->database->connection('game');

        return $this->journal->run($connection, $operationKey, $accountId, $hash,
            function () use ($connection, $accountId, $characterName, $type, $amount, $policy): CharacterServiceResult {
                return $this->applyPurchase($connection, $accountId, $characterName, $type, $amount, $policy);
            });
    }

    /**
     * Rechecks ownership, offline state, class and ranges within the receipt transaction.
     */
    private function applyPurchase(
        Connection $connection,
        string $accountId,
        string $characterName,
        CharacterServiceType $type,
        int $amount,
        ?PurchasedResetPolicy $policy,
    ): CharacterServiceResult {
        $status = $connection->table('MEMB_STAT')->where('memb___id', $accountId)->value('ConnectStat');

        if (GameAccountWriteStatus::blocksWrites($status)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterOnline);
        }

        $columns = ['CharacterIndex', 'Name', 'Class', 'cLevel', 'ResetCount', 'LevelUpPoint', 'Money'];
        foreach ($this->versionProfile->stats() as $definition) {
            $columns[] = $definition->databaseColumn;
        }
        $character = $connection->table('Character')->where('AccountID', $accountId)
            ->where('Name', $characterName)->lockForUpdate()->first($columns);

        if ($character === null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::CharacterNotFound, 404);
        }
        if ($amount < 1 || $amount > self::MAXIMUM_COUNTER) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidQuantity);
        }

        $before = $this->snapshot($character);
        $updates = $type === CharacterServiceType::ResetPurchase
            ? $this->resetUpdates($character, $amount, $policy)
            : $this->pointUpdates($character, $amount, $policy);
        $updated = $connection->table('Character')->where('AccountID', $accountId)
            ->where('CharacterIndex', $character->CharacterIndex)->where('Name', $characterName)->update($updates);

        if ($updated !== 1) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::OperationInProgress, 409);
        }
        foreach ($updates as $column => $value) {
            $character->{$column} = $value;
        }

        return new CharacterServiceResult($before, $this->snapshot($character));
    }

    /**
     * Calculates one KeepAll reset package without changing native quests, inventory or progression trees.
     *
     * @return array<string, int>
     */
    private function resetUpdates(stdClass $character, int $amount, ?PurchasedResetPolicy $policy): array
    {
        if ($policy === null || $policy->inventoryPolicy !== InventoryPolicy::KeepAll) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::ResetPolicyUnsupported);
        }
        $resets = (int) $character->ResetCount + $amount;
        if ($resets > min($policy->maximumResets, self::MAXIMUM_COUNTER)) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::MaximumResetsReached);
        }
        $points = $policy->pointsFor($resets);
        if ($points < 0 || $points > self::MAXIMUM_COUNTER) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::PointLimitExceeded);
        }
        $updates = ['cLevel' => 1, 'Experience' => 0, 'ResetCount' => $resets, 'LevelUpPoint' => $points];
        if ($policy->clearStats) {
            $base = $this->versionProfile->baseStatsForClass((int) $character->Class)->toArray();
            foreach ($this->versionProfile->statsForClass((int) $character->Class) as $definition) {
                $updates[$definition->databaseColumn] = $base[$definition->key];
            }
        }

        return $updates;
    }

    /**
     * Adds points only within the native signed INT boundary.
     *
     * @return array<string, int>
     */
    private function pointUpdates(stdClass $character, int $amount, ?PurchasedResetPolicy $policy): array
    {
        if ($policy !== null) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::IdempotencyConflict, 409);
        }
        $points = (int) $character->LevelUpPoint + $amount;
        if ($points < 0 || $points > self::MAXIMUM_COUNTER) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::PointLimitExceeded);
        }

        return ['LevelUpPoint' => $points];
    }

    /**
     * Captures every class-supported stat, including Dark Lord Command, for native audit and replay.
     *
     * @return array<string, int>
     */
    private function snapshot(stdClass $character): array
    {
        $snapshot = [
            'class_code' => (int) $character->Class,
            'level' => (int) $character->cLevel, 'resets' => (int) $character->ResetCount,
            'points' => (int) $character->LevelUpPoint, 'zen' => (int) $character->Money,
        ];
        try {
            $definitions = $this->versionProfile->statsForClass((int) $character->Class);
        } catch (InvalidArgumentException) {
            throw new CharacterServiceRejected(CharacterServiceBlockReason::InvalidClass);
        }
        foreach ($definitions as $definition) {
            $column = $definition->databaseColumn;
            $snapshot[$definition->key] = (int) $character->{$column};
        }

        return $snapshot;
    }
}
