<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Http::fake([
            'api.github.com/repos/*/*/releases/latest' => Http::response([
                'tag_name' => 'v0.0.1',
            ]),
            'https://www.microsoft.com' => Http::response('', 200, [
                'Date' => now()->toRfc7231String(),
            ]),
        ]);
    }

    public function test_admin_can_access_settings_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Maintenance mode');
    }

    public function test_admin_can_disable_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.settings.update'), $this->settingsPayload([
                'maintenance_mode' => '0',
            ]))
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse(app(ApplicationSettings::class)->maintenanceModeEnabled());
        $this->assertSame('0', Setting::query()->where('key', ApplicationSettings::MAINTENANCE_MODE_KEY)->value('value'));
    }

    public function test_login_stays_accessible_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $this
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Email');

        $this
            ->get('/')
            ->assertOk()
            ->assertSee('Email');
    }

    public function test_default_maintenance_message_is_translated(): void
    {
        $settings = app(ApplicationSettings::class);

        $settings->updateMaintenanceMode(true, null);

        app()->setLocale('fr');
        $this->assertSame(
            'L’application est temporairement en maintenance. Veuillez réessayer plus tard.',
            $settings->maintenanceMessage(),
        );

        app()->setLocale('en');
        $this->assertSame(
            'The application is temporarily under maintenance. Please try again later.',
            $settings->maintenanceMessage(),
        );
    }

    public function test_password_reset_routes_stay_accessible_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $this
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('Email');

        $this
            ->get(route('password.reset', 'test-token'))
            ->assertOk()
            ->assertSee('Email');
    }

    public function test_register_is_blocked_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $this
            ->get(route('register'))
            ->assertStatus(503)
            ->assertSee('Maintenance active.');

        $this
            ->post('/register', [
                'first_name' => 'Student',
                'last_name' => 'Example',
                'email' => 'student@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertStatus(503)
            ->assertSee('Maintenance active.');
    }

    public function test_teacher_is_blocked_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $teacher = User::factory()->teacher()->create([
            'is_admin' => false,
        ]);

        $this
            ->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertStatus(503)
            ->assertSee('Maintenance active.');
    }

    public function test_student_is_blocked_in_maintenance_mode(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $student = User::factory()->create([
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $this
            ->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertStatus(503)
            ->assertSee('Maintenance active.');
    }

    public function test_public_display_shows_maintenance_message(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'ab123',
        ]);

        $this
            ->get(route('public-display.show', $classroom->public_slug))
            ->assertStatus(503)
            ->assertSee('Maintenance active.');
    }

    public function test_public_display_json_returns_maintenance_message(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'ab123',
        ]);

        $this
            ->getJson(route('public-display.requests', $classroom->public_slug))
            ->assertStatus(503)
            ->assertJson([
                'message' => 'Maintenance active.',
            ]);
    }

    public function test_non_admin_user_is_blocked_after_login_during_maintenance_mode(): void
    {
        $this->enableMaintenanceMode('Maintenance active.');

        $student = User::factory()->create([
            'email' => 'student@example.com',
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $this
            ->post(route('login'), [
                'email' => $student->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('student.dashboard', absolute: false));

        $this
            ->get(route('student.dashboard'))
            ->assertStatus(503)
            ->assertSee('Maintenance active.');
    }

    public function test_behaviour_is_unchanged_when_maintenance_mode_is_disabled(): void
    {
        app(ApplicationSettings::class)->updateMaintenanceMode(false, null);

        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'ab123',
        ]);

        $this
            ->get(route('register'))
            ->assertOk();

        $this
            ->get(route('public-display.show', $classroom->public_slug))
            ->assertOk()
            ->assertSee($classroom->name);
    }

    private function enableMaintenanceMode(?string $message = null): void
    {
        app(ApplicationSettings::class)->updateMaintenanceMode(true, $message);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'display_name' => 'LineUp',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'auto_cancel_requests_enabled' => '0',
            'auto_cancel_requests_time' => '16:30',
            'priority_request_default_message' => '',
            'reuse_course_url_tab' => '0',
            'request_type_required' => '0',
            'maintenance_mode' => '1',
            'maintenance_message' => ApplicationSettings::DEFAULT_MAINTENANCE_MESSAGE,
        ], $overrides);
    }
}
