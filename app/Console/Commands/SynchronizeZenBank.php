<?php

namespace App\Console\Commands;

use App\Models\ZenBankOperation;
use App\ZenBank\Actions\SynchronizeZenBankOperation;
use App\ZenBank\ZenBankOperationStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zen-bank:sync')]
/**
 * Retries interrupted warehouse and web-ledger Zen transfers in bounded batches.
 */
#[Description('Retry pending MuOnline Zen Bank transfers.')]
final class SynchronizeZenBank extends Command
{
    /**
     * Executes one bounded synchronization pass.
     */
    public function handle(SynchronizeZenBankOperation $synchronizeZenBankOperation): int
    {
        if (! (bool) config('game.writes_enabled', false)
            || (bool) config('database.connections.game.readonly', true)) {
            $this->components->warn('Zen Bank synchronization skipped: game writes are disabled.');

            return self::SUCCESS;
        }

        $synchronized = 0;
        $pending = 0;
        $operations = ZenBankOperation::query()
            ->where('status', ZenBankOperationStatus::Pending->value)
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($operations as $operation) {
            if ($synchronizeZenBankOperation->execute($operation)) {
                $synchronized++;
            } else {
                $pending++;
            }
        }

        $this->components->info(
            "Zen Bank synchronization complete: {$synchronized} synced, {$pending} pending or conflicted.",
        );

        return self::SUCCESS;
    }
}
