<?php

namespace App\Console\Commands;

use App\CreditEarning\Actions\CollectOnlineTimeRewards;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('credits:collect-online-time')]
#[Description('Collect bounded online-time Credits for connected game accounts')]
/**
 * Collects one bounded scheduler observation for connected accounts.
 */
final class CollectOnlineTime extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CollectOnlineTimeRewards $collector): int
    {
        $observedAccounts = $collector->execute();
        $this->info(sprintf('Observed %d connected accounts.', $observedAccounts));

        return self::SUCCESS;
    }
}
