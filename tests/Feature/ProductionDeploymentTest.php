<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Protects the production container topology and its security boundary from regressions.
 */
class ProductionDeploymentTest extends TestCase
{
    /**
     * Confirms that production uses an isolated SQL Server database instead of container SQLite.
     */
    public function test_production_uses_the_isolated_sql_server_application_database(): void
    {
        $compose = $this->productionCompose();

        $this->assertStringContainsString('DB_CONNECTION: sqlsrv', $compose);
        $this->assertStringContainsString('DB_DATABASE: ${DB_DATABASE:-MuOnline_Web}', $compose);
        $this->assertStringContainsString('DB_HOST: ${DB_HOST:?', $compose);
        $this->assertStringContainsString('DB_USERNAME: ${DB_USERNAME:?', $compose);
        $this->assertStringContainsString('- app-storage:/var/www/html/storage', $compose);
        $this->assertStringNotContainsString('database.sqlite', $compose);
    }

    /**
     * Confirms that production sessions and health checks require the HTTPS application path.
     */
    public function test_production_runtime_enforces_secure_sessions_and_application_health(): void
    {
        $compose = $this->productionCompose();

        $this->assertStringContainsString('SESSION_SECURE_COOKIE: "true"', $compose);
        $this->assertStringContainsString('SESSION_HTTP_ONLY: "true"', $compose);
        $this->assertStringContainsString('https://127.0.0.1/up', $compose);
    }

    /**
     * Confirms that temporary database administrator secrets reach only the bootstrap app.
     */
    public function test_background_services_cannot_receive_database_bootstrap_credentials(): void
    {
        $productionCompose = $this->productionCompose();
        $localCompose = file_get_contents(base_path('docker/docker-compose.local.yaml'));

        $this->assertIsString($localCompose);
        $this->assertSame(2, substr_count($productionCompose, 'APP_DB_ADMIN_PASSWORD: ""'));
        $this->assertSame(1, substr_count($localCompose, 'APP_DB_ADMIN_PASSWORD: ""'));
        $this->assertSame(2, substr_count($productionCompose, 'APP_DB_BOOTSTRAP: "false"'));
        $this->assertSame(1, substr_count($localCompose, 'APP_DB_BOOTSTRAP: "false"'));
    }

    /**
     * Confirms that static assets retain the same browser security headers as dynamic pages.
     */
    public function test_nginx_static_assets_keep_browser_security_headers(): void
    {
        $productionNginx = file_get_contents(
            base_path('docker/nginx/prod/default.conf.template'),
        );
        $localNginx = file_get_contents(base_path('docker/nginx/local.conf'));

        $this->assertIsString($productionNginx);
        $this->assertIsString($localNginx);
        $this->assertGreaterThanOrEqual(2, substr_count($productionNginx, 'Permissions-Policy'));
        $this->assertGreaterThanOrEqual(2, substr_count($productionNginx, 'X-Content-Type-Options'));
        $this->assertGreaterThanOrEqual(2, substr_count($localNginx, 'Permissions-Policy'));
        $this->assertGreaterThanOrEqual(2, substr_count($localNginx, 'X-Content-Type-Options'));
    }

    /**
     * Reads the committed production Compose definition for focused assertions.
     */
    private function productionCompose(): string
    {
        $compose = file_get_contents(base_path('docker/docker-compose.prod.yaml'));

        $this->assertIsString($compose);

        return $compose;
    }
}
