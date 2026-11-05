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
     * Confirms vote-link and vote-history pages are separate protected tools.
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
            ->assertSee('Vote Links')
            ->assertDontSee('Account ID or vote site…');
        $this->actingAs($economyManager, 'admin')
            ->get(route('admin.vote-claims.index'))
            ->assertOk()
            ->assertSee('Vote History')
            ->assertSee('Account ID or vote site…')
            ->assertSee('Date from')
            ->assertSee('Date to');
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.vote-sites.index'))
            ->assertForbidden();
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.vote-claims.index'))
            ->assertForbidden();
    }

    /**
     * Confirms the review queue combines account search with a status filter.
     */
    public function test_vote_claim_queue_filters_by_search_and_status(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $matchingSite = VoteSite::factory()->create(['name_en' => 'Target Ranking']);
        $otherSite = VoteSite::factory()->create(['name_en' => 'Other Ranking']);
        VoteClaim::factory()->for($matchingSite, 'site')->create([
            'account_id' => 'target01',
            'status' => VoteClaimStatus::Pending,
        ]);
        VoteClaim::factory()->for($matchingSite, 'site')->create([
            'account_id' => 'target02',
            'status' => VoteClaimStatus::Rewarded,
        ]);
        VoteClaim::factory()->for($otherSite, 'site')->create([
            'account_id' => 'other001',
            'status' => VoteClaimStatus::Pending,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.vote-claims.index', [
                'search' => 'Target',
                'status' => VoteClaimStatus::Pending->value,
            ]));

        $response
            ->assertOk()
            ->assertSee('target01')
            ->assertSee('Internal rejection note')
            ->assertDontSee('target02')
            ->assertDontSee('other001')
            ->assertViewHas('claims', function ($claims): bool {
                return $claims->total() === 1
                    && $claims->first()->account_id === 'target01';
            });
    }

    /**
     * Confirms both boundary dates include their complete calendar days.
     */
    public function test_vote_claim_queue_filters_by_inclusive_date_range(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $site = VoteSite::factory()->create();
        VoteClaim::factory()->for($site, 'site')->create([
            'account_id' => 'before01',
            'initiated_at' => '2026-10-31 23:59:59',
        ]);
        VoteClaim::factory()->for($site, 'site')->create([
            'account_id' => 'start001',
            'initiated_at' => '2026-11-01 00:00:00',
        ]);
        VoteClaim::factory()->for($site, 'site')->create([
            'account_id' => 'end00001',
            'initiated_at' => '2026-11-02 23:59:59',
        ]);
        VoteClaim::factory()->for($site, 'site')->create([
            'account_id' => 'after001',
            'initiated_at' => '2026-11-03 00:00:00',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.vote-claims.index', [
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-02',
            ]));

        $response
            ->assertOk()
            ->assertSee('start001')
            ->assertSee('end00001')
            ->assertDontSee('before01')
            ->assertDontSee('after001')
            ->assertViewHas('claims', fn ($claims): bool => $claims->total() === 2);
    }

    /**
     * Confirms an inverted date range is rejected before querying history.
     */
    public function test_vote_claim_queue_rejects_inverted_date_range(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.vote-claims.index', [
                'date_from' => '2026-11-03',
                'date_to' => '2026-11-01',
            ]));

        $response->assertSessionHasErrors('date_to');
    }

    /**
     * Confirms the review queue uses bounded twenty-row pagination.
     */
    public function test_vote_claim_queue_is_paginated(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        VoteClaim::factory()->count(21)->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.vote-claims.index'));

        $response
            ->assertOk()
            ->assertViewHas('claims', function ($claims): bool {
                return $claims->count() === 20
                    && $claims->total() === 21
                    && $claims->lastPage() === 2;
            });
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
                'auto_reward_on_click' => '1',
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
            'auto_reward_on_click' => true,
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
