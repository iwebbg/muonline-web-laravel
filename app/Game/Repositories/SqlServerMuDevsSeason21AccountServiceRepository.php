<?php

namespace App\Game\Repositories;

use App\Economy\Exceptions\NativePurchaseRejected;
use App\Economy\NativeAccountMutation;
use App\Economy\NativeCurrency;
use App\Economy\NativePurchaseBlockReason;
use App\Economy\NativePurchaseKind;
use App\Economy\NativePurchaseResult;
use App\Game\Contracts\NativeAccountServiceRepository;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\MuDevsSeason21\AccountPurchaseJournalMuDevsSeason21;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

/**
 * Delivers native S21 coins and account VIP under an offline account lock and immutable receipts.
 */
final readonly class SqlServerMuDevsSeason21AccountServiceRepository implements NativeAccountServiceRepository
{
    private const MAXIMUM_BALANCE = 2_147_483_647;

    /**
     * Uses the native connection and the account purchase journal without accessing website wallets.
     */
    public function __construct(private DatabaseManager $database, private AccountPurchaseJournalMuDevsSeason21 $journal) {}

    /**
     * Resolves a Ruud target by ownership and stable native identity.
     */
    public function characterIndexFor(string $accountId, string $characterName): ?int
    {
        $index = $this->database->connection('game')->table('Character')
            ->where('AccountID', $accountId)->where('Name', $characterName)->value('CharacterIndex');

        return $index === null ? null : (int) $index;
    }

    /**
     * Replays receipts before evaluating current balances or applying another native grant.
     */
    public function apply(string $accountId, NativeAccountMutation $mutation, string $operationKey): NativePurchaseResult
    {
        $connection = $this->database->connection('game');
        $hash = hash('sha256', json_encode($mutation->toArray(), JSON_THROW_ON_ERROR));

        return $this->journal->run($connection, $operationKey, $accountId, $hash, function () use ($connection, $accountId, $mutation): NativePurchaseResult {
            $status = $connection->table('MEMB_STAT')->where('memb___id', $accountId)->value('ConnectStat');
            if (GameAccountWriteStatus::blocksWrites($status)
                || $connection->table('RestoreOffline')->where('Account', $accountId)->where(function ($query): void {
                    $query->whereNull('Status')->orWhere('Status', '<>', 0);
                })->exists()) {
                throw new NativePurchaseRejected(NativePurchaseBlockReason::AccountOnline);
            }
            if (! $connection->table('MEMB_INFO')->where('memb___id', $accountId)->exists()) {
                throw new NativePurchaseRejected(NativePurchaseBlockReason::AccountNotFound);
            }

            return $mutation->kind === NativePurchaseKind::CurrencyExchange
                ? $this->grantCurrency($connection, $accountId, $mutation)
                : $this->grantVip($connection, $accountId, $mutation);
        });
    }

    /**
     * Adds only the selected currency using a fixed column allow-list and signed INT bounds.
     */
    private function grantCurrency(Connection $connection, string $accountId, NativeAccountMutation $mutation): NativePurchaseResult
    {
        if ($mutation->currency === null || $mutation->amount < 1 || $mutation->amount > self::MAXIMUM_BALANCE
            || $mutation->vipLevel !== null || $mutation->durationDays !== 0) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
        }
        $column = match ($mutation->currency) {
            NativeCurrency::WCoinC => 'WCoinC',
            NativeCurrency::WCoinP => 'WCoinP',
            NativeCurrency::GoblinPoints => 'GoblinPoint',
            NativeCurrency::LuckyCoins => 'LuckyCoin',
            NativeCurrency::Ruud => 'RuudMoney',
        };
        if ($mutation->currency === NativeCurrency::Ruud) {
            $query = $connection->table('Character')->where('AccountID', $accountId)
                ->where('CharacterIndex', $mutation->characterIndex);
            if ($mutation->characterIndex === null || ! $query->exists()) {
                throw new NativePurchaseRejected(NativePurchaseBlockReason::CharacterNotFound);
            }
        } else {
            if ($mutation->characterIndex !== null) {
                throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
            }
            $table = $mutation->currency === NativeCurrency::LuckyCoins ? 'LuckyCoin' : 'CashShopData';
            $query = $connection->table($table)->where('AccountID', $accountId);
            if (! $query->exists()) {
                $values = $table === 'LuckyCoin'
                    ? ['AccountID' => $accountId, 'LuckyCoin' => 0]
                    : ['AccountID' => $accountId, 'WCoinC' => 0, 'WCoinP' => 0, 'GoblinPoint' => 0];
                $connection->table($table)->insert($values);
            }
        }
        $balance = (int) $query->lockForUpdate()->value($column);
        $resultingBalance = $balance + $mutation->amount;
        if ($balance < 0 || $resultingBalance > self::MAXIMUM_BALANCE) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::BalanceLimit);
        }
        $updated = $query->update([$column => $resultingBalance]);
        if ($updated !== 1) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::PurchasePending);
        }

        return new NativePurchaseResult(
            ['currency' => $mutation->currency->value, 'balance' => $balance, 'character_index' => $mutation->characterIndex],
            ['currency' => $mutation->currency->value, 'balance' => $resultingBalance, 'character_index' => $mutation->characterIndex],
        );
    }

    /**
     * Extends matching active VIP or starts an expired membership using the database's own wall clock.
     */
    private function grantVip(Connection $connection, string $accountId, NativeAccountMutation $mutation): NativePurchaseResult
    {
        if (! in_array($mutation->vipLevel, [1, 2, 3], true) || $mutation->durationDays < 1 || $mutation->durationDays > 3650
            || $mutation->currency !== null || $mutation->amount !== 0 || $mutation->characterIndex !== null) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
        }
        $query = $connection->table('MEMB_INFO')->where('memb___id', $accountId);
        $account = $query->lockForUpdate()->first(['AccountLevel', 'AccountExpireDate']);
        $clock = $connection->table('MEMB_INFO')->where('memb___id', $accountId)->selectRaw('CURRENT_TIMESTAMP as server_time')->value('server_time');
        $now = CarbonImmutable::parse((string) $clock, 'UTC');
        $expires = $account->AccountExpireDate === null ? null : CarbonImmutable::parse((string) $account->AccountExpireDate, 'UTC');
        $active = (int) $account->AccountLevel > 0 && $expires !== null && $expires->greaterThanOrEqualTo($now);
        if ($active && (int) $account->AccountLevel !== $mutation->vipLevel) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::DifferentVipTier);
        }
        $base = $active ? $expires : $now;
        $newExpiry = $base->addDays($mutation->durationDays)->format('Y-m-d H:i:s');
        if ($newExpiry > '2079-06-06 23:59:00') {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
        }
        $before = ['account_level' => (int) $account->AccountLevel, 'expires_at' => $account->AccountExpireDate];
        $query->update(['AccountLevel' => $mutation->vipLevel, 'AccountExpireDate' => $newExpiry]);
        $saved = $connection->table('MEMB_INFO')->where('memb___id', $accountId)->first(['AccountLevel', 'AccountExpireDate']);

        return new NativePurchaseResult($before, ['account_level' => (int) $saved->AccountLevel, 'expires_at' => $saved->AccountExpireDate]);
    }
}
