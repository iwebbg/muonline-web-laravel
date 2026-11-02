<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\Administrator;
use App\Models\VoteClaim;
use App\Models\VoteSite;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies vote configuration permissions, validation and review endpoints.
 */
final class AdminVoteRewardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only economy managers can open vote reward administration.
     */
    public function test_vote_reward_manager_requires_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get(route('admin.vote-sites.index'))
            ->assertOk()
            ->assertSee('Vote rewards');
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.vote-sites.index'))
            ->assertForbidden();
    }

    /**
     * Confirms an economy manager can create a bilingual vote destination.
     */
    public function test_economy_manager_creates_vote_destination(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'admin-vote-site-create-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.vote-sites.store'), [
                '_token' => $csrfToken,
                'slug' => 'top-ranking',
                'name_en' => 'Top Ranking',
                'name_bg' => 'Топ класация',
                'url' => 'https://ranking.example/vote/1',
                'image_url' => 'https://ranking.example/banner.png',
                'cooldown_hours' => 12,
                'reward_credits' => 20,
                'reward_zen' => 2_000_000,
                'enabled' => '1',
                'sort_order' => 10,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vote_sites', [
            'slug' => 'top-ranking',
            'name_bg' => 'Топ класация',
            'reward_credits' => 20,
            'reward_zen' => 2_000_000,
            'enabled' => true,
        ]);
    }

    /**
     * Confirms a destination cannot silently award no currency or use an unsafe URL.
     */
    public function test_vote_destination_rejects_empty_reward_and_unsafe_url(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'admin-vote-site-invalid-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.vote-sites.store'), [
                '_token' => $csrfToken,
                'slug' => 'unsafe-ranking',
                'name_en' => 'Unsafe Ranking',
                'url' => 'javascript:alert(1)',
                'cooldown_hours' => 12,
                'reward_credits' => 0,
                'reward_zen' => 0,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors(['url', 'reward_credits']);
        $this->assertSame(0, VoteSite::query()->count());
    }

    /**
     * Confirms approval through the protected endpoint commits the reward audit.
     */
    public function test_economy_manager_approves_pending_claim(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $claim = VoteClaim::factory()->create([
            'account_id' => 'adminvote',
            'reward_credits' => 30,
            'reward_zen' => 0,
        ]);
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('adminvote')->andReturnTrue();
        $csrfToken = 'admin-vote-approve-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.vote-claims.review', $claim), [
                '_token' => $csrfToken,
                'decision' => 'approve',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(VoteClaimStatus::Rewarded, $claim->refresh()->status);
        $this->assertSame(30, AccountWallet::query()->sole()->balance);
    }

    /**
     * Confirms rejection records a reason and leaves balances unchanged.
     */
    public function test_economy_manager_rejects_claim_without_reward(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $claim = VoteClaim::factory()->create();
        $csrfToken = 'admin-vote-reject-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.vote-claims.review', $claim), [
                '_token' => $csrfToken,
                'decision' => 'reject',
                'reason' => 'Vote could not be verified.',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(VoteClaimStatus::Rejected, $claim->refresh()->status);
        $this->assertSame('Vote could not be verified.', $claim->rejection_reason);
        $this->assertSame(0, AccountWallet::query()->count());
    }
}
