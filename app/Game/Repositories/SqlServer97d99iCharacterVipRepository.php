<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterVipRepository;
use App\Game\GameAccountWriteStatus;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipPurchaseBlockReason;
use DateTimeInterface;
use Illuminate\Database\DatabaseManager;

/**
 * Synchronizes per-character VIP state to the Darksteam 97d+99i schema.
 */
final readonly class SqlServer97d99iCharacterVipRepository implements CharacterVipRepository
{
    /**
     * Creates the repository with Laravel's game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Updates an offline character after repeating ownership inside the write boundary.
     *
     * @throws VipPurchaseRejected When the character is missing, unowned or online.
     */
    public function synchronize(
        string $accountId,
        string $characterName,
        DateTimeInterface $expiresAt,
    ): void {
        $connection = $this->database->connection('game');

        $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $expiresAt,
        ): void {
            $accountStatus = $connection
                ->table('MEMB_STAT')
                ->where('memb___id', $accountId)
                ->lockForUpdate()
                ->first(['ConnectStat']);

            if (GameAccountWriteStatus::blocksWrites($accountStatus->ConnectStat ?? null)) {
                throw new VipPurchaseRejected(VipPurchaseBlockReason::CharacterOnline);
            }

            $character = $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->lockForUpdate()
                ->first(['Name']);

            if ($character === null) {
                throw new VipPurchaseRejected(VipPurchaseBlockReason::CharacterNotFound);
            }

            $isActive = $expiresAt->getTimestamp() > now()->getTimestamp();

            $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update([
                    'IsVip' => $isActive ? 1 : 0,
                    'VipExpirationTime' => $isActive
                        ? $expiresAt->getTimestamp()
                        : 0,
                ]);
        }, attempts: 3);
    }
}
