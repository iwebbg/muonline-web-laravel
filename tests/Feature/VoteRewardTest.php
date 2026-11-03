<?php

namespace Tests\Feature;

use App\Admin\Audit\AuditLogReader;
use App\Auth\GameAccount;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\AccountZenBalance;
use App\Models\Administrator;
use App\Models\VoteClaim;
use App\Models\VoteSite;
use App\Models\WalletTransaction;
use App\VoteRewards\Actions\AwardVoteClaim;
use App\VoteRewards\Actions\CreateVoteClaim;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use App\VoteRewards\VoteClaimBlockReason;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies account isolation, cooldowns and atomic vote reward settlement.
 */
final class VoteRewardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms an authenticated visit creates a pending claim before redirecting away.
     */
    public function test_account_visit_creates_pending_claim_and_redirects_to_destination(): void
    {
        $site = VoteSite::factory()->create([
            'url' => 'https://ranking.example/vote/server',
        ]);
        $csrfToken = 'vote-claim-create-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('voteuser'))
            ->post(route('account.votes.claims.store', $site), [
                '_token' => $csrfToken,
                'idempotency_key' => 'da69e84e-a687-4d9c-bd3d-7e912ca0d584',
            ]);

        $response->assertRedirect('https://ranking.example/vote/server');
        $this->assertDatabaseHas('vote_claims', [
            'vote_site_id' => $site->getKey(),
            'account_id' => 'voteuser',
            'status' => VoteClaimStatus::Pending->value,
            'reward_credits' => 10,
            'reward_zen' => 1_000_000,
        ]);
    }

    /**
     * Confirms pending and rewarded claims enforce account cooldown independently.
     */
    public function test_pending_and_rewarded_claims_block_duplicate_account_visits(): void
    {
        $site = VoteSite::factory()->create(['cooldown_hours' => 12]);
        $action = $this->app->make(CreateVoteClaim::class);
        $firstClaim = $action->execute(
            voteSite: $site,
            accountId: 'cooldown1',
            ipAddress: '192.0.2.1',
            idempotencyKey: '56e23866-46ea-4f63-a30d-18698d9e2f48',
        );

        try {
            $action->execute(
                voteSite: $site,
                accountId: 'cooldown1',
                ipAddress: '192.0.2.2',
                idempotencyKey: 'aa5cf5da-5026-4b6c-89fe-68589d591036',
            );
            $this->fail('A second pending claim must be rejected.');
        } catch (VoteClaimRejected $exception) {
            $this->assertSame(VoteClaimBlockReason::PendingClaim, $exception->reason);
        }

        $firstClaim->update([
            'status' => VoteClaimStatus::Rewarded,
            'rewarded_at' => now(),
            'reviewed_at' => now(),
        ]);

        try {
            $action->execute(
                voteSite: $site,
                accountId: 'cooldown1',
                ipAddress: '192.0.2.2',
                idempotencyKey: 'df0edbd2-875a-4a61-9978-a5d2d204b81b',
            );
            $this->fail('A rewarded account inside the cooldown must be rejected.');
        } catch (VoteClaimRejected $exception) {
            $this->assertSame(VoteClaimBlockReason::AccountCooldown, $exception->reason);
        }

        $this->assertSame(1, VoteClaim::query()->count());
    }

    /**
     * Confirms a rewarded network cannot immediately claim through another account.
     */
    public function test_rewarded_claim_enforces_hashed_ip_cooldown_across_accounts(): void
    {
        $site = VoteSite::factory()->create(['cooldown_hours' => 12]);
        $action = $this->app->make(CreateVoteClaim::class);
        $claim = $action->execute(
            voteSite: $site,
            accountId: 'network1',
            ipAddress: '198.51.100.10',
            idempotencyKey: 'bb332ef1-c117-425c-bdfb-139590dc07fb',
        );
        $claim->update([
            'status' => VoteClaimStatus::Rewarded,
            'rewarded_at' => now(),
            'reviewed_at' => now(),
        ]);

        try {
            $action->execute(
                voteSite: $site,
                accountId: 'network2',
                ipAddress: '198.51.100.10',
                idempotencyKey: 'f6385441-5291-4cab-9c62-a1d44d0d6af6',
            );
            $this->fail('A rewarded network inside the cooldown must be rejected.');
        } catch (VoteClaimRejected $exception) {
            $this->assertSame(VoteClaimBlockReason::IpCooldown, $exception->reason);
        }
    }

    /**
     * Confirms approval atomically appends Credits and Zen audit values exactly once.
     */
    public function test_approval_atomically_awards_credits_and_zen_once(): void
    {
        $administrator = Administrator::factory()->create();
        $claim = VoteClaim::factory()->create([
            'account_id' => 'reward01',
            'reward_credits' => 25,
            'reward_zen' => 2_000_000,
        ]);
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('reward01')->andReturnTrue();
        $action = $this->app->make(AwardVoteClaim::class);

        $rewardedClaim = $action->execute($claim, $administrator);

        $this->assertSame(VoteClaimStatus::Rewarded, $rewardedClaim->status);
        $this->assertSame(25, AccountWallet::query()->sole()->balance);
        $this->assertSame(2_000_000, AccountZenBalance::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(
            $administrator->getKey(),
            WalletTransaction::query()->sole()->performed_by_administrator_id,
        );
        $this->assertSame(0, $rewardedClaim->zen_balance_before);
        $this->assertSame(2_000_000, $rewardedClaim->zen_balance_after);

        try {
            $action->execute($claim->refresh(), $administrator);
            $this->fail('An already rewarded claim must not be settled twice.');
        } catch (VoteClaimRejected $exception) {
            $this->assertSame(VoteClaimBlockReason::AlreadyReviewed, $exception->reason);
        }

        $this->assertSame(25, AccountWallet::query()->sole()->balance);
        $this->assertSame(2_000_000, AccountZenBalance::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms vote settlement is visible through the normalized administrator audit log.
     */
    public function test_vote_claim_is_exposed_as_a_normalized_audit_event(): void
    {
        $claim = VoteClaim::factory()->create([
            'account_id' => 'auditvote',
            'reward_credits' => 15,
            'reward_zen' => 500_000,
            'status' => VoteClaimStatus::Rewarded,
            'zen_balance_before' => 100,
            'zen_balance_after' => 500_100,
            'reviewed_at' => now(),
            'rewarded_at' => now(),
        ]);

        $entries = $this->app->make(AuditLogReader::class)->latest(type: 'vote_reward');

        $this->assertCount(1, $entries);
        $this->assertSame('vote_reward', $entries[0]['type']);
        $this->assertSame('auditvote', $entries[0]['subject']);
        $this->assertSame($claim->reward_zen, $entries[0]['contextSnapshot']['reward_zen']);
    }

    /**
     * Confirms an expired claim is audited without changing either balance.
     */
    public function test_expired_claim_is_not_rewarded(): void
    {
        $administrator = Administrator::factory()->create();
        $claim = VoteClaim::factory()->create([
            'account_id' => 'expired1',
            'expires_at' => now()->subMinute(),
        ]);

        try {
            $this->app->make(AwardVoteClaim::class)->execute($claim, $administrator);
            $this->fail('An expired claim must not be rewarded.');
        } catch (VoteClaimRejected $exception) {
            $this->assertSame(VoteClaimBlockReason::ClaimExpired, $exception->reason);
        }

        $this->assertSame(VoteClaimStatus::Expired, $claim->refresh()->status);
        $this->assertSame(0, AccountWallet::query()->count());
        $this->assertSame(0, AccountZenBalance::query()->count());
    }

    /**
     * Confirms the vote page displays only the authenticated account's history.
     */
    public function test_vote_page_isolates_claim_history_by_account(): void
    {
        $site = VoteSite::factory()->create(['name_en' => 'Realm Ranking']);
        VoteClaim::factory()->for($site, 'site')->create(['account_id' => 'visible1']);
        VoteClaim::factory()->for($site, 'site')->create(['account_id' => 'hidden01']);

        $response = $this
            ->actingAs($this->account('visible1'))
            ->get(route('account.votes.index'));

        $response
            ->assertOk()
            ->assertSee('Realm Ranking')
            ->assertViewHas('claims', fn ($claims): bool => $claims->count() === 1
                && $claims->first()->account_id === 'visible1');
    }

    /**
     * Builds one in-memory legacy account for authenticated feature requests.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Vote Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
