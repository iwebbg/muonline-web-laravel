<?php

namespace App\Console\Commands;

use App\CreditEarning\Actions\SyncCreditItemExchange;
use App\CreditEarning\CreditExchangeStatus;
use App\Models\CreditItemExchange;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('credits:sync-item-exchanges')]
#[Description('Resume pending and extracted item-to-Credits exchanges')]
/**
 * Retries incomplete item exchanges after transient or process failures.
 */
final class SyncCreditItemExchanges extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncCreditItemExchange $synchronizer): int
    {
        CreditItemExchange::query()
            ->whereIn('status', [CreditExchangeStatus::Pending, CreditExchangeStatus::Extracted])
            ->oldest('id')
            ->limit(100)
            ->get()
            ->each(function (CreditItemExchange $exchange) use ($synchronizer): void {
                try {
                    $synchronizer->execute($exchange);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return self::SUCCESS;
    }
}
