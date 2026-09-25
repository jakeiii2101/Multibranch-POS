<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseSecurityTest extends TestCase
{
    public function test_web_responses_include_safe_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_environment_template_uses_neutral_local_url_and_secure_sessions(): void
    {
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_NAME="SniperPOS"', $environment);
        $this->assertStringContainsString('APP_URL=http://localhost:8080', $environment);
        $this->assertStringNotContainsString('app.github.dev', $environment);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $environment);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $environment);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $environment);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $environment);
    }
}
