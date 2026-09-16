<?php

namespace Tests\Feature;

use Tests\TestCase;

class VercelDeploymentConfigTest extends TestCase
{
    public function test_vercel_container_configuration_is_present_and_production_safe(): void
    {
        $dockerfilePath = base_path('Dockerfile.vercel');
        $caddyfilePath = base_path('Caddyfile');
        $dockerignorePath = base_path('.dockerignore');

        $this->assertFileExists($dockerfilePath);
        $this->assertFileExists($caddyfilePath);
        $this->assertFileExists($dockerignorePath);

        $dockerfile = file_get_contents($dockerfilePath);
        $caddyfile = file_get_contents($caddyfilePath);
        $dockerignore = file_get_contents($dockerignorePath);

        $this->assertStringContainsString('dunglas/frankenphp:1-php8.4-bookworm', $dockerfile);
        $this->assertStringContainsString('install-php-extensions', $dockerfile);
        $this->assertStringContainsString('pdo_mysql', $dockerfile);
        $this->assertStringContainsString('npm run build', $dockerfile);
        $this->assertStringContainsString('--no-dev', $dockerfile);

        $this->assertStringContainsString(':{$PORT:80}', $caddyfile);
        $this->assertStringContainsString('root * /app/public', $caddyfile);
        $this->assertStringContainsString('php_server', $caddyfile);

        $this->assertStringContainsString('.env', $dockerignore);
        $this->assertStringContainsString('node_modules', $dockerignore);
        $this->assertStringContainsString('vendor', $dockerignore);
    }
}
