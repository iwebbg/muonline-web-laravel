<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\EconomySetting;
use App\Models\EconomySettingRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies paid-service settings are restricted, validated and audited.
 */
final class EconomySettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms unauthenticated visitors cannot read or update economy settings.
     */
    public function test_guest_cannot_access_economy_settings(): void
    {
        $this->get(route('admin.economy-settings.edit'))->assertRedirect(route('admin.login'));

        $this->put(route('admin.economy-settings.update'), [])->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('economy_settings', 0);
    }

    /**
     * Confirms an economy administrator can save one complete audited revision.
     */
    public function test_administrator_saves_paid_service_settings_with_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'economy-settings-token';
        $this->actingAs($administrator, 'admin');

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->put(route('admin.economy-settings.update'), [
                '_token' => $csrfToken,
                'itemGeneratorMarkupPercentage' => 20,
                'characterRenameEnabled' => true,
                'characterRenamePriceCredits' => 500,
                'characterClassChangeEnabled' => true,
                'characterClassChangePriceCredits' => 1_000,
            ]);

        $response
            ->assertRedirect(route('admin.economy-settings.edit'))
            ->assertSessionHas('status', __('ui.admin.economy_settings_saved'));
        $settings = EconomySetting::query()->sole();
        $revision = EconomySettingRevision::query()->sole();
        $this->assertSame(20, $settings->item_generator_markup_percentage);
        $this->assertSame(500, $settings->character_rename_price_credits);
        $this->assertSame(1, $revision->revision);
        $this->assertSame($administrator->getKey(), $revision->changed_by_administrator_id);
    }
}
