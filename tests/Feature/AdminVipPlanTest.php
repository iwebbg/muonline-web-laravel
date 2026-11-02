<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\VipPlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies VIP plan management permissions, validation and lifecycle behavior.
 */
final class AdminVipPlanTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only economy managers can open the VIP plan administration page.
     */
    public function test_vip_plan_manager_requires_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get('/admin/economy/vip-plans')
            ->assertOk()
            ->assertSee('Character VIP plans');
        $this->actingAs($moderator, 'admin')
            ->get('/admin/economy/vip-plans')
            ->assertForbidden();
    }

    /**
     * Confirms an economy manager can create a bilingual player-visible plan.
     */
    public function test_economy_manager_creates_vip_plan(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'admin-vip-plan-create-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/economy/vip-plans', [
                '_token' => $csrfToken,
                'slug' => 'thirty-days',
                'name_en' => 'Thirty Days',
                'name_bg' => 'Тридесет дни',
                'description_en' => 'Darksteam character VIP.',
                'description_bg' => 'Darksteam VIP за герой.',
                'duration_days' => 30,
                'price_credits' => 1000,
                'enabled' => '1',
                'sort_order' => 10,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vip_plans', [
            'slug' => 'thirty-days',
            'name_bg' => 'Тридесет дни',
            'duration_days' => 30,
            'price_credits' => 1000,
            'enabled' => true,
        ]);
    }

    /**
     * Confirms invalid pricing and duration are rejected before persistence.
     */
    public function test_vip_plan_rejects_invalid_economy_values(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'admin-vip-plan-invalid-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/economy/vip-plans', [
                '_token' => $csrfToken,
                'slug' => 'invalid-plan',
                'name_en' => 'Invalid Plan',
                'duration_days' => 0,
                'price_credits' => -1,
                'sort_order' => 0,
            ]);

        $response->assertSessionHasErrors(['duration_days', 'price_credits']);
        $this->assertSame(0, VipPlan::query()->count());
    }

    /**
     * Confirms plan removal is soft and no longer visible through normal queries.
     */
    public function test_economy_manager_soft_deletes_vip_plan(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $plan = VipPlan::factory()->create();
        $csrfToken = 'admin-vip-plan-delete-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->delete('/admin/economy/vip-plans/'.$plan->getKey(), [
                '_token' => $csrfToken,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted('vip_plans', [
            'id' => $plan->getKey(),
        ]);
    }
}
