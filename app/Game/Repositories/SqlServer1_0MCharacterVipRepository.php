<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterVipRepository;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipPurchaseBlockReason;
use DateTimeInterface;

/**
 * Rejects character VIP synchronization unsupported by the stock DarksTeam 1.0M schema.
 */
final readonly class SqlServer1_0MCharacterVipRepository implements CharacterVipRepository
{
    /**
     * Rejects the write before any unsupported game column can be addressed.
     *
     * @throws VipPurchaseRejected Always, because this profile has no character VIP storage.
     */
    public function synchronize(
        string $accountId,
        string $characterName,
        DateTimeInterface $expiresAt,
    ): void {
        throw new VipPurchaseRejected(VipPurchaseBlockReason::PlanUnavailable);
    }
}
