<?php

namespace Tests\Feature;

use App\Models\WebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Verifies shared account feature components preserve safe rendering behavior.
 */
final class AccountUiComponentTest extends TestCase
{
    /**
     * Confirms stat allocation supports precise corrections and fast bulk additions.
     */
    public function test_stat_panel_renders_fine_and_bulk_allocation_steps(): void
    {
        $html = Blade::render('<x-account-stats-panel />');

        $this->assertStringContainsString('class="mt-0.5 flex gap-1 text-xs text-steel"', $html);
        $this->assertStringContainsString('x-text="`+ ${allocations[statDefinition.key].toLocaleString()}`"', $html);
        $this->assertStringContainsString('@click="removePoints(statDefinition.key, 10)">−10', $html);
        $this->assertStringContainsString('@click="removePoints(statDefinition.key, 1)">−1', $html);
        $this->assertStringContainsString('@click="allocatePoints(statDefinition.key, 1)">+1', $html);
        $this->assertStringContainsString('@click="allocatePoints(statDefinition.key, 10)">+10', $html);
        $this->assertStringContainsString('@click="allocatePoints(statDefinition.key, 100)">+100', $html);
        $this->assertStringContainsString('@click="allocatePoints(statDefinition.key, 1000)">+1000', $html);
    }

    /**
     * Confirms the feature header escapes content and links back to the account panel.
     */
    public function test_feature_header_renders_escaped_content_and_back_link(): void
    {
        $html = Blade::render(
            '<x-account-feature-header kicker="Vault" heading="<unsafe>" intro="Safe intro" />',
        );

        $this->assertStringContainsString('Vault', $html);
        $this->assertStringContainsString('&lt;unsafe&gt;', $html);
        $this->assertStringNotContainsString('<unsafe>', $html);
        $this->assertStringContainsString('href="http://localhost/account"', $html);
    }

    /**
     * Confirms one custody card reuses exact item state and disables unsafe writes.
     */
    public function test_custody_card_renders_status_and_disabled_delete_action(): void
    {
        $storedItem = new WebStorageItem;
        $storedItem->forceFill([
            'item_snapshot' => [
                'name' => 'Excellent <Blade>',
                'image' => '/images/items/97d_99i/0000.gif',
                'level' => 11,
            ],
            'status' => WebStorageItemStatus::Available,
            'last_sync_error' => null,
        ]);
        $html = Blade::render(
            '<x-custody-item-card :stored-item="$storedItem" action-route="/withdraw" action-label="Withdraw" :writes-enabled="false" />',
            ['storedItem' => $storedItem],
        );

        $this->assertStringContainsString('Excellent &lt;Blade&gt; +11', $html);
        $this->assertStringContainsString('Available', $html);
        $this->assertStringContainsString('action="/withdraw"', $html);
        $this->assertStringContainsString('value="DELETE"', $html);
        $this->assertStringContainsString('disabled', $html);
    }
}
