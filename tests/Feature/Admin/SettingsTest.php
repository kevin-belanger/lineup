<?php

namespace Tests\Feature\Admin;

use App\Models\RequestType;
use App\Models\Setting;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ApplicationSettings;
use DateTimeZone;
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
            'reuse_course_url_tab' => '1',
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
        $this->assertTrue($settings->reuseCourseUrlTab());
        $this->assertFalse($settings->requestTypeRequired());

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Atelier Algo')
            ->assertSee('Default language')
            ->assertSee('Application version')
            ->assertSee('America/Vancouver')
            ->assertSee('16:45')
            ->assertSee('Please support the assessment room.')
            ->assertSee('Reuse the same tab when opening a course URL')
            ->assertSee('reuse_course_url_tab', false);
    }

    public function test_settings_use_complete_php_timezone_list_with_utc_default(): void
    {
        Http::fake([
            'api.github.com/repos/*/*/tags*' => Http::response([]),
        ]);

        $admin = User::factory()->admin()->create();
        $settings = app(ApplicationSettings::class);

        $this->assertSame('UTC', $settings->timezone());
        $this->assertSame(DateTimeZone::listIdentifiers(DateTimeZone::ALL), $settings->availableTimezones());

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('UTC')
            ->assertSee('x-model="search"', false)
            ->assertSee('filteredTimezones', false)
            ->assertSee('@click.outside="open = false"', false)
            ->assertSee('Search time zones')
            ->assertSee('Start typing to search time zones.');
    }

    public function test_existing_configured_timezone_is_preserved(): void
    {
        Setting::query()->create([
            'key' => ApplicationSettings::TIMEZONE_KEY,
            'value' => 'America/Toronto',
        ]);

        $this->assertSame('America/Toronto', app(ApplicationSettings::class)->timezone());
    }

    public function test_admin_can_save_timezone_from_complete_php_timezone_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'LineUp',
            'default_locale' => 'en',
            'timezone' => 'Europe/Paris',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
            'priority_request_default_message' => '',
            'reuse_course_url_tab' => '0',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Europe/Paris', app(ApplicationSettings::class)->timezone());
    }

    public function test_course_url_tab_reuse_is_disabled_by_default(): void
    {
        $settings = app(ApplicationSettings::class);

        $this->assertFalse($settings->reuseCourseUrlTab());
        $this->assertSame('_blank', $settings->courseUrlTarget());
        $this->assertSame('noopener noreferrer', $settings->courseUrlRel());
    }

    public function test_admin_can_disable_course_url_tab_reuse(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(ApplicationSettings::class);
        $settings->updateReuseCourseUrlTab(true);

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'display_name' => 'LineUp',
            'default_locale' => 'en',
            'timezone' => 'America/Toronto',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
            'priority_request_default_message' => '',
            'reuse_course_url_tab' => '0',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse($settings->reuseCourseUrlTab());
        $this->assertSame('_blank', $settings->courseUrlTarget());
    }

    public function test_admin_can_manage_request_types_when_saving_settings_without_changing_existing_requests(): void
    {
        Http::fake([
            'api.github.com/repos/*/*/tags*' => Http::response([]),
        ]);

        $admin = User::factory()->admin()->create();
        $existing = RequestType::query()->create([
            'name' => 'Explanation',
            'sort_order' => 1,
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'request_type' => 'Explanation',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Request types')
            ->assertSee('Explanation')
            ->assertSee('request_types[]', false)
            ->assertDontSee(route('admin.settings.update').'/request-types');

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'display_name' => 'LineUp',
                'default_locale' => 'en',
                'timezone' => 'America/Toronto',
                'auto_cancel_requests_enabled' => '0',
                'auto_cancel_requests_time' => '16:30',
                'priority_request_default_message' => '',
                'reuse_course_url_tab' => '0',
                'request_type_required' => '1',
                'request_types' => [
                    ' Correction ',
                    'Validation',
                ],
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('toast');

        $this->assertDatabaseMissing('request_types', [
            'id' => $existing->id,
        ]);
        $this->assertSame(
            ['Correction', 'Validation'],
            RequestType::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertTrue(app(ApplicationSettings::class)->requestTypeRequired());
        $this->assertSame('Explanation', $supportRequest->refresh()->request_type);
    }

    public function test_request_type_required_setting_is_disabled_when_no_request_types_are_configured(): void
    {
        $admin = User::factory()->admin()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'display_name' => 'LineUp',
                'default_locale' => 'en',
                'timezone' => 'America/Toronto',
                'auto_cancel_requests_enabled' => '0',
                'auto_cancel_requests_time' => '16:30',
                'priority_request_default_message' => '',
                'reuse_course_url_tab' => '0',
                'request_type_required' => '1',
                'request_types' => [],
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse(app(ApplicationSettings::class)->requestTypeRequired());
    }

    public function test_request_type_changes_are_validated_with_the_main_settings_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'display_name' => 'LineUp',
                'default_locale' => 'en',
                'timezone' => 'America/Toronto',
                'auto_cancel_requests_enabled' => '0',
                'auto_cancel_requests_time' => '16:30',
                'priority_request_default_message' => '',
                'reuse_course_url_tab' => '0',
                'request_types' => [
                    'Correction',
                    ' Correction ',
                ],
            ])
            ->assertSessionHasErrors('request_types.1');

        $this->assertDatabaseCount('request_types', 0);
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
