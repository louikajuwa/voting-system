<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $res = $this->get('/login');

        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');

        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("connect-src 'self' ws: wss:", $csp);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $csp);
    }

    public function test_hsts_only_on_secure_requests(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_spoofed_forwarded_for_from_untrusted_peer_is_ignored(): void
    {
        Route::get('/_ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('X-Forwarded-For', '1.2.3.4')
            ->get('/_ip')
            ->assertSee('203.0.113.9');
    }

    public function test_forwarded_for_from_trusted_proxy_is_honoured(): void
    {
        Route::get('/_ip', fn () => request()->ip());

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeader('X-Forwarded-For', '198.51.100.7')
            ->get('/_ip')
            ->assertSee('198.51.100.7');
    }
}
