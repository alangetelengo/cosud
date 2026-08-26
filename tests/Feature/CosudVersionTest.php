<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CosudVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cosud_version_config_has_semantic_default(): void
    {
        $this->assertSame('1.0.0', config('cosud.version'));
        $this->assertSame('2026-08-26', config('cosud.released_at'));
    }

    public function test_cosud_version_command_displays_version(): void
    {
        $this->artisan('cosud:version')
            ->expectsOutputToContain('COSUD 1.0.0')
            ->assertSuccessful();
    }

    public function test_login_page_displays_version(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('v1.0.0', false);
    }
}
