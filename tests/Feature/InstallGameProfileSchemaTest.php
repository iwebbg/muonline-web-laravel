<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verifies safety guards around game-profile schema installation.
 */
final class InstallGameProfileSchemaTest extends TestCase
{
    /**
     * Confirms the command cannot mutate a database when bootstrap mode is closed.
     */
    public function test_rejects_installation_when_bootstrap_mode_is_disabled(): void
    {
        config()->set('application_database.bootstrap.enabled', false);
        config()->set('game.profile.key', 'darksteam_1_0m');

        $this->artisan('game:install-profile-schema')
            ->expectsOutputToContain('Set APP_DB_BOOTSTRAP=true')
            ->assertFailed();
    }

    /**
     * Confirms an unrelated game profile cannot receive 1.0M extensions.
     */
    public function test_rejects_installation_for_another_profile(): void
    {
        config()->set('application_database.bootstrap.enabled', true);
        config()->set('game.profile.key', 'darksteam_97d99i');

        $this->artisan('game:install-profile-schema')
            ->expectsOutputToContain('supports only darksteam_1_0m')
            ->assertFailed();
    }
}
