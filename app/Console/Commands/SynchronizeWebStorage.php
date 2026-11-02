<?php

namespace App\Console\Commands;

use App\Models\WebStorageItem;
use App\WebStorage\Actions\SynchronizeWebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('web-storage:sync')]
/**
 * Retries interrupted web-vault extractions and withdrawals in bounded batches.
 */
#[Description('Retry pending MuOnline web storage item movements.')]
final class SynchronizeWebStorage extends Command
{
    /**
     * Executes one bounded synchronization pass.
     */
    public function handle(SynchronizeWebStorageItem $synchronizeWebStorageItem): int
    {
        if (! (bool) config('game.writes_enabled', false)
            || (bool) config('database.connections.game.readonly', true)) {
            $this->components->warn('Web storage synchronization skipped: game writes are disabled.');

            return self::SUCCESS;
        }

        $synchronized = 0;
        $pending = 0;
        $items = WebStorageItem::query()
            ->whereIn('status', [
                WebStorageItemStatus::Storing->value,
                WebStorageItemStatus::WithdrawalPending->value,
            ])
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($items as $item) {
            if ($synchronizeWebStorageItem->execute($item)) {
                $synchronized++;
            } else {
                $pending++;
            }
        }

        $this->components->info(
            "Web storage synchronization complete: {$synchronized} synced, {$pending} pending retry.",
        );

        return self::SUCCESS;
    }
}
