<?php

namespace App\CreditEarning\Actions;

use App\Models\CreditItemExchange;

/**
 * Resumes one recoverable item exchange from its durable operation state.
 */
final readonly class SyncCreditItemExchange
{
    /**
     * Creates the synchronizer with the shared exchange action.
     */
    public function __construct(
        private ExchangeItemsForCredits $exchangeItemsForCredits,
    ) {}

    /**
     * Attempts to finish inventory extraction and the Credits award idempotently.
     */
    public function execute(CreditItemExchange $exchange): CreditItemExchange
    {
        return $this->exchangeItemsForCredits->perform($exchange);
    }
}
