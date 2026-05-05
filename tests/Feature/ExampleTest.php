<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_shows_login_for_guests(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Log in');
    }

    public function test_public_home_redirects_authenticated_users_to_dashboard_redirector(): void
    {
        $user = User::factory()->teacher()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
