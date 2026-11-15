<?php

namespace App\Game\Repositories;

use App\Game\Contracts\AccountMembershipReadRepository;
use App\Game\Data\AccountMembership;
use Illuminate\Database\DatabaseManager;

/**
 * Reads S21 account-wide VIP expiry without executing its mutating account-level procedure.
 */
final readonly class SqlServerMuDevsSeason21AccountMembershipReadRepository implements AccountMembershipReadRepository
{
    /**
     * Uses the native game connection and its own clock for membership validity.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Mirrors the strict expiry comparison in WZ_GetAccountLevel using a SELECT only.
     *
     * Missing dates and invalid negative tiers remain unknown. SQL Server's
     * CURRENT_TIMESTAMP uses the same clock as the supplied procedure's GETDATE,
     * avoiding assumptions about the Windows database server's time zone.
     */
    public function forAccount(string $accountId): ?AccountMembership
    {
        $row = $this->database->connection('game')
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->select(['AccountLevel', 'AccountExpireDate'])
            ->selectRaw(
                'CASE WHEN AccountLevel = 0 THEN 0 '
                .'WHEN AccountLevel IS NULL OR AccountLevel < 0 OR AccountExpireDate IS NULL THEN NULL '
                .'WHEN CURRENT_TIMESTAMP > AccountExpireDate THEN 0 '
                .'ELSE AccountLevel END as effective_level',
            )
            ->selectRaw(
                'CASE WHEN AccountLevel > 0 AND CURRENT_TIMESTAMP > AccountExpireDate '
                .'THEN 1 ELSE 0 END as expired',
            )
            ->first();

        if ($row === null) {
            return null;
        }

        return new AccountMembership(
            storedLevel: $row->AccountLevel === null ? null : (int) $row->AccountLevel,
            effectiveLevel: $row->effective_level === null ? null : (int) $row->effective_level,
            expiresAt: $row->AccountExpireDate === null ? null : (string) $row->AccountExpireDate,
            expired: (int) $row->expired === 1,
        );
    }
}
