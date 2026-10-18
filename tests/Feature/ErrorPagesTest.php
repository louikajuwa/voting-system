<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_renders_the_branded_page(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('/css/ballotbox.css', false);
    }

    public function test_all_custom_error_views_render_standalone(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view("errors.$code")->render();
            $this->assertStringContainsString((string) $code, $html);
            $this->assertStringContainsString('ballotbox.css', $html);
            $this->assertStringNotContainsString('csrf-token', $html); // no session dependency
        }
    }
}
