<?php

namespace Tests\Feature;

use App\Http\AccountPanelConfiguration;
use App\Models\GameServer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the JavaScript account panel receives stable server-owned configuration.
 */
final class AccountPanelConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms every operation uses its named route and request safety values.
     */
    public function test_configuration_exposes_named_routes_and_request_safety_values(): void
    {
        $configuration = $this->app
            ->make(AccountPanelConfiguration::class)
            ->toArray(
                writesEnabled: true,
                csrfToken: 'known-csrf-token',
            );

        $this->assertSame(route('account.reset-preview'), $configuration['previewUrl']);
        $this->assertSame(route('account.character-reset'), $configuration['executeUrl']);
        $this->assertSame(route('account.grand-reset-preview'), $configuration['grandResetPreviewUrl']);
        $this->assertSame(route('account.character-grand-reset'), $configuration['grandResetExecuteUrl']);
        $this->assertSame(route('account.stat-reset-preview'), $configuration['statResetPreviewUrl']);
        $this->assertSame(route('account.character-stat-reset'), $configuration['statResetExecuteUrl']);
        $this->assertSame(route('account.pk-clear-preview'), $configuration['pkClearPreviewUrl']);
        $this->assertSame(route('account.character-pk-clear'), $configuration['pkClearExecuteUrl']);
        $this->assertSame(route('account.move-to-town-preview'), $configuration['moveToTownPreviewUrl']);
        $this->assertSame(route('account.character-move'), $configuration['moveToTownExecuteUrl']);
        $this->assertSame(route('account.stat-allocation-preview'), $configuration['statPreviewUrl']);
        $this->assertSame(route('account.stat-allocations.store'), $configuration['statExecuteUrl']);
        $this->assertSame(route('account.item-storage'), $configuration['itemStorageUrl']);
        $this->assertTrue($configuration['writesEnabled']);
        $this->assertSame('known-csrf-token', $configuration['csrfToken']);
    }

    /**
     * Confirms labels follow the active locale and preserve English fallback keys.
     */
    public function test_configuration_builds_bulgarian_operation_and_item_labels(): void
    {
        app()->setLocale('bg');

        $configuration = $this->app
            ->make(AccountPanelConfiguration::class)
            ->toArray(
                writesEnabled: false,
                csrfToken: 'csrf-token',
            );

        $this->assertFalse($configuration['writesEnabled']);
        $this->assertSame('Героят беше ресетнат успешно.', $configuration['completed']);
        $this->assertSame('Издръжливост', $configuration['itemOptionLabels']['durability']);
        $this->assertSame('Екипировката и чантата се запазват', $configuration['inventoryPolicies']['keep_all']);
        $this->assertSame('Фиксирана базова цена', $configuration['pkClearPriceModes']['fixed']);
    }

    /**
     * Confirms account blockers use the administrator-managed server version.
     */
    public function test_configuration_builds_version_aware_block_reasons(): void
    {
        GameServer::factory()->current()->create([
            'version' => 'Season 6',
        ]);

        $configuration = $this->app
            ->make(AccountPanelConfiguration::class)
            ->toArray(
                writesEnabled: true,
                csrfToken: 'csrf-token',
            );

        $this->assertStringContainsString(
            'Season 6 item codec',
            $configuration['executionReasons']['inventory_policy_unsupported'],
        );
        $this->assertStringContainsString(
            'Season 6 stat-limit mapping',
            $configuration['statBlockReasons']['character_class_unsupported'],
        );
    }
}
