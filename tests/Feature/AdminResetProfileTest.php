<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\Administrator;
use App\Models\ResetProfile;
use App\Models\ResetProfileRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies secured and audited administration of the reset policy draft.
 */
final class AdminResetProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms that guests cannot change server reset settings.
     */
    public function test_guest_cannot_update_reset_profile(): void
    {
        $response = $this->putWithCsrf('/admin/reset-profile', $this->validPayload());

        $response->assertRedirect('/admin/login');
        $this->assertDatabaseCount('reset_profiles', 0);
    }

    /**
     * Confirms that an authenticated game account never gains administration rights.
     */
    public function test_game_account_cannot_update_reset_profile(): void
    {
        $gameAccount = new GameAccount(
            guid: 1,
            username: 'player',
            displayName: 'Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );

        $this->actingAs($gameAccount);

        $response = $this->putWithCsrf('/admin/reset-profile', $this->validPayload());

        $response->assertRedirect('/admin/login');
        $this->assertDatabaseCount('reset_profiles', 0);
    }

    /**
     * Confirms that one valid update stores typed settings and a complete audit snapshot.
     */
    public function test_administrator_can_store_first_reset_profile_revision(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin');

        $response = $this->putJsonWithCsrf('/admin/reset-profile', $this->validPayload());

        $response
            ->assertOk()
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('settings.inventoryPolicy', 'clear_bag')
            ->assertJsonPath('settings.maximumStat', 30_000)
            ->assertJsonPath('settings.grandResetRequiredResets', 100)
            ->assertJsonPath('settings.grandResetPointsMode', 'add_fixed');

        $profile = ResetProfile::query()->sole();
        $revision = ResetProfileRevision::query()->sole();

        $this->assertSame(350, $profile->required_level);
        $this->assertSame(25_000_000, $profile->base_zen_cost);
        $this->assertSame(30_000, $profile->maximum_stat);
        $this->assertSame(100, $profile->grand_reset_required_resets);
        $this->assertSame(350, $profile->grand_reset_required_level);
        $this->assertSame(999, $profile->maximum_grand_resets);
        $this->assertSame(500, $profile->grand_reset_points);
        $this->assertSame($administrator->id, $profile->updated_by_administrator_id);
        $this->assertSame(1, $revision->revision);
        $this->assertSame(15_000_000, $revision->before_snapshot['baseZenCost']);
        $this->assertSame(25_000_000, $revision->after_snapshot['baseZenCost']);
        $this->assertSame($administrator->id, $revision->changed_by_administrator_id);
    }

    /**
     * Confirms that later updates increment the revision and retain the previous snapshot.
     */
    public function test_later_update_creates_next_revision_with_previous_state(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin');
        $this->putJsonWithCsrf('/admin/reset-profile', $this->validPayload())->assertOk();

        $updatedPayload = [
            ...$this->validPayload(),
            'requiredLevel' => 400,
            'pointsPerReset' => 750,
        ];

        $response = $this->putJsonWithCsrf('/admin/reset-profile', $updatedPayload);

        $response
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('settings.requiredLevel', 400);

        $latestRevision = ResetProfileRevision::query()
            ->where('revision', 2)
            ->sole();

        $this->assertSame(350, $latestRevision->before_snapshot['requiredLevel']);
        $this->assertSame(400, $latestRevision->after_snapshot['requiredLevel']);
        $this->assertDatabaseCount('reset_profile_revisions', 2);
    }

    /**
     * Confirms that invalid input cannot partially update the profile or audit history.
     */
    public function test_invalid_reset_profile_is_rejected_without_database_writes(): void
    {
        $administrator = Administrator::factory()->create();
        $invalidPayload = [
            ...$this->validPayload(),
            'requiredLevel' => 401,
            'maximumStat' => 32_768,
            'inventoryPolicy' => 'delete_database',
        ];

        $this->actingAs($administrator, 'admin');

        $response = $this->putJsonWithCsrf('/admin/reset-profile', $invalidPayload);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requiredLevel',
                'maximumStat',
                'inventoryPolicy',
            ]);
        $this->assertDatabaseCount('reset_profiles', 0);
        $this->assertDatabaseCount('reset_profile_revisions', 0);
    }

    /**
     * Confirms grand-reset progression cannot require unreachable normal resets.
     */
    public function test_grand_reset_requirement_cannot_exceed_maximum_resets(): void
    {
        $administrator = Administrator::factory()->create();
        $invalidPayload = [
            ...$this->validPayload(),
            'maximumResets' => 99,
            'grandResetRequiredResets' => 100,
        ];

        $this->actingAs($administrator, 'admin');

        $response = $this->putJsonWithCsrf('/admin/reset-profile', $invalidPayload);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grandResetRequiredResets');
        $this->assertDatabaseCount('reset_profiles', 0);
        $this->assertDatabaseCount('reset_profile_revisions', 0);
    }

    /**
     * Confirms that clients cannot choose revision or audit ownership fields.
     */
    public function test_administrator_cannot_override_server_managed_audit_fields(): void
    {
        $administrator = Administrator::factory()->create();
        $payload = [
            ...$this->validPayload(),
            'revision' => 9000,
            'updated_by_administrator_id' => null,
        ];

        $this->actingAs($administrator, 'admin');

        $this->putJsonWithCsrf('/admin/reset-profile', $payload)->assertOk();

        $profile = ResetProfile::query()->sole();

        $this->assertSame(1, $profile->revision);
        $this->assertSame($administrator->id, $profile->updated_by_administrator_id);
    }

    /**
     * Returns one valid reset policy payload at the HTTP boundary.
     *
     * @return array<string, bool|int|string>
     */
    private function validPayload(): array
    {
        return [
            'enabled' => true,
            'requiredLevel' => 350,
            'maximumResets' => 100,
            'maximumStat' => 30_000,
            'grandResetRequiredResets' => 100,
            'baseZenCost' => 25_000_000,
            'multiplyCostByReset' => true,
            'clearStats' => true,
            'pointsMode' => 'cumulative',
            'pointsPerReset' => 500,
            'inventoryPolicy' => 'clear_bag',
            'grandResetEnabled' => true,
            'grandResetRequiredLevel' => 350,
            'maximumGrandResets' => 999,
            'grandResetBaseZenCost' => 5_000_000,
            'grandResetMultiplyCost' => false,
            'grandResetClearStats' => false,
            'grandResetPointsMode' => 'add_fixed',
            'grandResetPoints' => 500,
            'grandResetInventoryPolicy' => 'keep_all',
            'grandResetConsumeRequiredResets' => true,
        ];
    }

    /**
     * Sends a form request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function putWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'admin-reset-profile-test-token';
        $payload = [
            ...$data,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->put($uri, $payload);
    }

    /**
     * Sends a JSON request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function putJsonWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'admin-reset-profile-json-test-token';
        $payload = [
            ...$data,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->putJson($uri, $payload);
    }
}
