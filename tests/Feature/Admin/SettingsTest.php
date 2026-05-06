<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_application_display_name(): void
    {
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'Atelier Algo',
            'timezone' => 'America/Vancouver',
            'auto_cancel_requests_enabled' => '1',
            'auto_cancel_requests_time' => '16:45',
        ]);

        $response
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('toast');

        $settings = app(ApplicationSettings::class);

        $this->assertSame('Atelier Algo', $settings->displayName());
        $this->assertSame('America/Vancouver', $settings->timezone());
        $this->assertTrue($settings->autoCancelRequestsEnabled());
        $this->assertSame('16:45', $settings->autoCancelRequestsTime());

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Atelier Algo')
            ->assertSee('America/Vancouver')
            ->assertSee('16:45');
    }

    public function test_application_display_name_is_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => '',
        ]);

        $response->assertSessionHasErrors('display_name');
    }

    public function test_auto_cancel_time_is_required_when_auto_cancel_is_enabled(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'LineUp',
            'timezone' => 'America/Toronto',
            'auto_cancel_requests_enabled' => '1',
            'auto_cancel_requests_time' => '',
        ]);

        $response->assertSessionHasErrors('auto_cancel_requests_time');
    }

    public function test_application_timezone_must_be_valid(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'LineUp',
            'timezone' => 'Not/AZone',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
        ]);

        $response->assertSessionHasErrors('timezone');
    }

    public function test_non_admin_can_not_update_application_settings(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('admin.settings.update'), [
                'display_name' => 'Nope',
            ])
            ->assertForbidden();
    }
}
