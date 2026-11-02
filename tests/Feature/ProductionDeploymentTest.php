<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Protects the production container topology and its security boundary from regressions.
 */
class ProductionDeploymentTest extends TestCase
{
    /**
     * Confirms that persistent SQLite data cannot hide migrations shipped in a new image.
     */
    public function test_production_database_persists_inside_storage_without_hiding_migrations(): void
    {
        $compose = $this->productionCompose();

        $this->assertStringContainsString(
            'DB_DATABASE: ${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}',
            $compose,
        );
        $this->assertStringContainsString('- app-storage:/var/www/html/storage', $compose);
        $this->assertStringNotContainsString('app-database:/var/www/html/database', $compose);
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
