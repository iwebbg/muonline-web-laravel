<?php

namespace App\CreditEarning\Actions;

use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Data\OnlineAccountSession;
use App\Models\OnlineTimeAccount;
use App\Shop\EconomySettingReader;
use App\Shop\EconomySettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Converts bounded observations of current game sessions into claimable Credits.
 */
final readonly class CollectOnlineTimeRewards
{
    private const MAXIMUM_OBSERVATION_GAP_SECONDS = 180;

    /**
     * Creates the collector with session, settings and application persistence boundaries.
     */
    public function __construct(
        private OnlineAccountRepository $onlineAccountRepository,
        private EconomySettingReader $economySettingReader,
        private DatabaseManager $database,
    ) {}

    /**
     * Accounts one scheduler observation without backfilling unknown downtime.
     */
    public function execute(?CarbonImmutable $observedAt = null): int
    {
        $settings = $this->economySettingReader->current();

        if (! $settings->onlineTimeRewardsEnabled) {
            return 0;
        }

        $observationTime = $observedAt ?? CarbonImmutable::now();
        $sessions = $this->onlineAccountRepository->onlineSessions();

        foreach ($sessions as $session) {
            $this->accountSession($session, $observationTime, $settings);
        }

        OnlineTimeAccount::query()
            ->whereNotNull('session_started_at')
            ->where(function ($query) use ($observationTime): void {
                $query
                    ->whereNull('last_observed_at')
                    ->orWhere('last_observed_at', '<', $observationTime);
            })
            ->update([
                'session_started_at' => null,
                'accounted_until' => null,
            ]);

        return count($sessions);
    }

    /**
     * Advances one account checkpoint and converts only complete configured units.
     */
    private function accountSession(
        OnlineAccountSession $session,
        CarbonImmutable $observedAt,
        EconomySettings $settings,
    ): void {
        $this->database->transaction(function () use ($session, $observedAt, $settings): void {
            $account = OnlineTimeAccount::query()
                ->where('account_id', $session->accountId)
                ->lockForUpdate()
                ->first();

            if (! $account instanceof OnlineTimeAccount) {
                OnlineTimeAccount::query()->create([
                    'account_id' => $session->accountId,
                    'session_started_at' => $session->connectedAt,
                    'accounted_until' => $observedAt,
                    'last_observed_at' => $observedAt,
                    'reward_date' => $observedAt->toDateString(),
                ]);

                return;
            }

            if (
                $account->session_started_at === null
                || ! $account->session_started_at->equalTo($session->connectedAt)
            ) {
                $account->update([
                    'session_started_at' => $session->connectedAt,
                    'accounted_until' => $observedAt,
                    'last_observed_at' => $observedAt,
                    'reward_date' => $observedAt->toDateString(),
                    'rewarded_seconds_today' => $this->todayCounter($account, $observedAt, 'rewarded_seconds_today'),
                    'rewarded_credits_today' => $this->todayCounter($account, $observedAt, 'rewarded_credits_today'),
                ]);

                return;
            }

            $accountedUntil = $account->accounted_until ?? $observedAt;
            $elapsedSeconds = max(0, (int) $accountedUntil->diffInSeconds($observedAt, false));
            $observedSeconds = min($elapsedSeconds, self::MAXIMUM_OBSERVATION_GAP_SECONDS);
            $rewardedCreditsToday = $this->todayCounter($account, $observedAt, 'rewarded_credits_today');
            $rewardedSecondsToday = $this->todayCounter($account, $observedAt, 'rewarded_seconds_today');
            $totalSeconds = $account->remainder_seconds + $observedSeconds;
            $possibleUnits = intdiv($totalSeconds, $settings->onlineSecondsPerUnit());
            $remainingDailyCredits = max(0, $settings->onlineDailyCreditLimit - $rewardedCreditsToday);
            $availableUnits = intdiv($remainingDailyCredits, $settings->onlineCreditsPerUnit);
            $rewardedUnits = min($possibleUnits, $availableUnits);
            $rewardedCredits = $rewardedUnits * $settings->onlineCreditsPerUnit;
            $consumedSeconds = $rewardedUnits * $settings->onlineSecondsPerUnit();
            $remainderSeconds = $totalSeconds - $consumedSeconds;

            if ($possibleUnits > $availableUnits) {
                $remainderSeconds %= $settings->onlineSecondsPerUnit();
            }

            $account->update([
                'accounted_until' => $observedAt,
                'last_observed_at' => $observedAt,
                'reward_date' => $observedAt->toDateString(),
                'rewarded_seconds_today' => $rewardedSecondsToday + $consumedSeconds,
                'rewarded_credits_today' => $rewardedCreditsToday + $rewardedCredits,
                'remainder_seconds' => $remainderSeconds,
                'claimable_credits' => $account->claimable_credits + $rewardedCredits,
                'lifetime_seconds' => $account->lifetime_seconds + $observedSeconds,
            ]);
        }, attempts: 3);
    }

    /**
     * Returns a daily counter only when it belongs to the current reward date.
     */
    private function todayCounter(
        OnlineTimeAccount $account,
        CarbonImmutable $observedAt,
        string $attribute,
    ): int {
        if ($account->reward_date?->toDateString() !== $observedAt->toDateString()) {
            return 0;
        }

        return (int) $account->getAttribute($attribute);
    }
}
