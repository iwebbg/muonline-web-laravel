<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\ResetProfile;
use App\Models\ResetProfileRevision;
use App\Reset\ResetProfileSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies focused, immediate and audited character-rule profile updates.
 */
final class AdminResetProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests cannot view or update a character-rule profile.
     */
    public function test_guest_cannot_access_character_rule_profiles(): void
    {
        $this->get('/admin/character-rules/reset')->assertRedirect('/admin/login');
        $this->putWithCsrf('/admin/character-rules/reset', $this->resetPayload())->assertRedirect('/admin/login');

        $this->assertDatabaseCount('reset_profiles', 0);
    }

    /**
     * Confirms every focused editor is reachable from the character-rules navigation.
     */
    public function test_administrator_can_open_each_character_rule_profile(): void
    {
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        foreach (['reset', 'grand-reset', 'stat-reset', 'pk-clear', 'move-to-town'] as $profile) {
            $this->get('/admin/character-rules/'.$profile)
                ->assertOk()
                ->assertSee('aria-modal="true"', false)
                ->assertSee(__('ui.admin.revision_changes'))
                ->assertSee(route('admin.character-rules.edit', ['profile' => $profile]));
        }
    }

    /**
     * Confirms a reset update is active immediately and leaves other profile defaults intact.
     */
    public function test_reset_profile_update_changes_only_reset_settings(): void
    {
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $response = $this->putJsonWithCsrf('/admin/character-rules/reset', $this->resetPayload());

        $response
            ->assertOk()
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('settings.requiredLevel', 400)
            ->assertJsonPath('settings.grandResetRequiredLevel', 350);
        $profile = ResetProfile::query()->sole();
        $revision = ResetProfileRevision::query()->sole();

        $this->assertSame(400, $profile->required_level);
        $this->assertSame(350, $profile->grand_reset_required_level);
        $this->assertSame(1, $revision->revision);
        $this->assertSame($administrator->id, $revision->changed_by_administrator_id);
    }

    /**
     * Confirms Grand Reset settings are merged without overwriting a saved normal Reset profile.
     */
    public function test_grand_reset_profile_update_preserves_reset_settings(): void
    {
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');
        $this->putJsonWithCsrf('/admin/character-rules/reset', $this->resetPayload())->assertOk();

        $response = $this->putJsonWithCsrf('/admin/character-rules/grand-reset', $this->grandResetPayload());

        $response
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('settings.grandResetRequiredLevel', 400)
            ->assertJsonPath('settings.requiredLevel', 400);
        $profile = ResetProfile::query()->sole();

        $this->assertSame(400, $profile->required_level);
        $this->assertSame(400, $profile->grand_reset_required_level);
        $this->assertSame(2, $profile->revision);
        $this->assertDatabaseCount('reset_profile_revisions', 2);
    }

    /**
     * Confirms Stats Reset settings can be applied without changing another section.
     */
    public function test_stat_reset_profile_update_changes_only_stat_reset_settings(): void
    {
        $profile = $this->createDefaultProfile();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $this->putJsonWithCsrf('/admin/character-rules/stat-reset', [
            'statResetEnabled' => false,
            'statResetRequiredLevel' => 200,
            'statResetRequiredResets' => 25,
            'statResetZenCost' => 7_000_000,
        ])->assertOk();

        $profile->refresh();
        $this->assertFalse($profile->stat_reset_enabled);
        $this->assertSame(7_000_000, $profile->stat_reset_zen_cost);
        $this->assertSame(100, $profile->pk_clear_required_level);
    }

    /**
     * Confirms PK Clear settings can be applied without changing another section.
     */
    public function test_pk_clear_profile_update_changes_only_pk_clear_settings(): void
    {
        $profile = $this->createDefaultProfile();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $this->putJsonWithCsrf('/admin/character-rules/pk-clear', [
            'pkClearEnabled' => true,
            'pkClearRequiredLevel' => 250,
            'pkClearBaseZenCost' => 900_000,
            'pkClearPriceMode' => 'by_pk_count',
        ])->assertOk();

        $profile->refresh();
        $this->assertSame(250, $profile->pk_clear_required_level);
        $this->assertSame('by_pk_count', $profile->pk_clear_price_mode->value);
        $this->assertSame(0, $profile->move_to_town_map);
    }

    /**
     * Confirms Move to Town settings can be applied without changing another section.
     */
    public function test_move_to_town_profile_update_changes_only_move_settings(): void
    {
        $profile = $this->createDefaultProfile();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $this->putJsonWithCsrf('/admin/character-rules/move-to-town', [
            'moveToTownEnabled' => true,
            'moveToTownMap' => 3,
            'moveToTownX' => 10,
            'moveToTownY' => 20,
            'moveToTownZenCost' => 50_000,
        ])->assertOk();

        $profile->refresh();
        $this->assertSame(3, $profile->move_to_town_map);
        $this->assertSame(10, $profile->move_to_town_x);
        $this->assertSame(100, $profile->pk_clear_required_level);
    }

    /**
     * Confirms invalid input cannot create a partial profile or audit revision.
     */
    public function test_invalid_focused_profile_is_rejected_without_database_writes(): void
    {
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $this->putJsonWithCsrf('/admin/character-rules/pk-clear', [
            'pkClearEnabled' => true,
            'pkClearRequiredLevel' => 401,
            'pkClearBaseZenCost' => -1,
            'pkClearPriceMode' => 'unsafe',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'pkClearRequiredLevel',
            'pkClearBaseZenCost',
            'pkClearPriceMode',
        ]);

        $this->assertDatabaseCount('reset_profiles', 0);
        $this->assertDatabaseCount('reset_profile_revisions', 0);
    }

    /**
     * Confirms profile identifiers outside the fixed navigation set return not found.
     */
    public function test_unknown_character_rule_profile_returns_not_found(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')
            ->get('/admin/character-rules/not-real')
            ->assertNotFound();
    }

    /**
     * Creates the active default profile used to prove isolated updates.
     */
    private function createDefaultProfile(): ResetProfile
    {
        return ResetProfile::query()->create([
            ...ResetProfileSettings::defaults()->toPersistenceArray(),
            'revision' => 0,
            'updated_by_administrator_id' => null,
        ]);
    }

    /**
     * Returns one valid normal Reset section payload.
     *
     * @return array<string, bool|int|string>
     */
    private function resetPayload(): array
    {
        return [
            'enabled' => true,
            'requiredLevel' => 400,
            'maximumResets' => 999,
            'maximumStat' => 30_000,
            'baseZenCost' => 25_000_000,
            'multiplyCostByReset' => true,
            'clearStats' => true,
            'pointsMode' => 'cumulative',
            'pointsPerReset' => 500,
            'inventoryPolicy' => 'clear_bag',
        ];
    }

    /**
     * Returns one valid Grand Reset section payload.
     *
     * @return array<string, bool|int|string>
     */
    private function grandResetPayload(): array
    {
        return [
            'grandResetEnabled' => true,
            'grandResetRequiredLevel' => 400,
            'grandResetRequiredResets' => 200,
            'maximumGrandResets' => 500,
            'grandResetBaseZenCost' => 10_000_000,
            'grandResetMultiplyCost' => true,
            'grandResetClearStats' => true,
            'grandResetPointsMode' => 'fixed',
            'grandResetPoints' => 1_000,
            'grandResetInventoryPolicy' => 'clear_all',
            'grandResetConsumeRequiredResets' => true,
        ];
    }

    /**
     * Sends one form request with a matching CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function putWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'character-rule-form-token';

        return $this
            ->withSession(['_token' => $csrfToken])
            ->put($uri, [...$data, '_token' => $csrfToken]);
    }

    /**
     * Sends one JSON request with a matching CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function putJsonWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'character-rule-json-token';

        return $this
            ->withSession(['_token' => $csrfToken])
            ->putJson($uri, [...$data, '_token' => $csrfToken]);
    }
}
