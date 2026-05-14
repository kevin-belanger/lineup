<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_application_display_name(): void
    {
        Http::fake([
            'api.github.com/repos/*/*/tags*' => Http::response([
                ['name' => 'v0.0.1'],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'Atelier Algo',
            'default_locale' => 'en',
            'timezone' => 'America/Vancouver',
            'auto_cancel_requests_enabled' => '1',
            'auto_cancel_requests_time' => '16:45',
            'priority_request_default_message' => 'Please support the assessment room.',
        ]);

        $response
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('toast');

        $settings = app(ApplicationSettings::class);

        $this->assertSame('Atelier Algo', $settings->displayName());
        $this->assertSame('en', $settings->defaultLocale());
        $this->assertSame('America/Vancouver', $settings->timezone());
        $this->assertTrue($settings->autoCancelRequestsEnabled());
        $this->assertSame('16:45', $settings->autoCancelRequestsTime());
        $this->assertSame('Please support the assessment room.', $settings->priorityRequestDefaultMessage());

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Atelier Algo')
            ->assertSee('Default language')
            ->assertSee('Application version')
            ->assertSee('America/Vancouver')
            ->assertSee('16:45')
            ->assertSee('Please support the assessment room.');
    }

    public function test_default_priority_request_message_is_empty_by_default(): void
    {
        $this->assertSame('', app(ApplicationSettings::class)->priorityRequestDefaultMessage());
    }

    public function test_admin_can_clear_default_priority_request_message(): void
    {
        $admin = User::factory()->admin()->create();

        app(ApplicationSettings::class)->updatePriorityRequestDefaultMessage('Temporary default message');

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'LineUp',
            'default_locale' => 'en',
            'timezone' => 'America/Toronto',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
            'priority_request_default_message' => '',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('', app(ApplicationSettings::class)->priorityRequestDefaultMessage());
    }

    public function test_admin_settings_show_available_application_update(): void
    {
        config([
            'app.version' => 'v0.0.1',
            'app.repository_url' => 'https://github.com/kevin-belanger/lineup',
        ]);

        Http::fake([
            'api.github.com/repos/kevin-belanger/lineup/tags*' => Http::response([
                ['name' => 'not-a-version'],
                ['name' => 'v0.1.0-beta'],
                ['name' => 'v0.0.10'],
                ['name' => 'v0.0.2'],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Application version')
            ->assertSee('Installed version')
            ->assertSee('v0.0.1')
            ->assertSee('Latest available version')
            ->assertSee('v0.1.0-beta')
            ->assertSee('A newer version is available.')
            ->assertSee('Run update.sh on the server to update the application.')
            ->assertSee(config('app.repository_url').'#updating-the-application', false);
    }

    public function test_admin_settings_show_up_to_date_application_status(): void
    {
        config([
            'app.version' => 'v0.0.1',
            'app.repository_url' => 'https://github.com/kevin-belanger/lineup',
        ]);

        Http::fake([
            'api.github.com/repos/kevin-belanger/lineup/tags*' => Http::response([
                ['name' => 'v0.0.1'],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('The application is up to date.');
    }

    public function test_admin_settings_do_not_crash_when_update_check_fails(): void
    {
        Http::fake([
            'api.github.com/repos/*/*/tags*' => Http::response([], 500),
        ]);

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Unable to check for updates at this time.');
    }

    public function test_admin_settings_handle_non_version_installed_value(): void
    {
        config([
            'app.version' => 'dev',
            'app.repository_url' => 'https://github.com/kevin-belanger/lineup',
        ]);

        Http::fake([
            'api.github.com/repos/kevin-belanger/lineup/tags*' => Http::response([
                ['name' => 'v0.0.1'],
            ]),
        ]);

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('dev')
            ->assertSee('Unable to determine whether this installation is up to date.');
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
            'default_locale' => 'en',
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
            'default_locale' => 'en',
            'timezone' => 'Not/AZone',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
        ]);

        $response->assertSessionHasErrors('timezone');
    }

    public function test_teacher_can_not_access_application_settings(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this
            ->actingAs($teacher)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();

        $this
            ->actingAs($teacher)
            ->patch(route('admin.settings.update'), [
                'display_name' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_student_can_not_update_application_settings(): void
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
