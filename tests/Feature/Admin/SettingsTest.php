<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Atelier Algo')
            ->assertSee('Default language')
            ->assertSee('Application version')
            ->assertSee('America/Vancouver')
            ->assertSee('16:45')
            ->assertSee('Database backup')
            ->assertSee(route('admin.database.backup.download'), false);
    }

    public function test_admin_can_download_database_backup(): void
    {
        config([
            'app.version' => 'v0.0.3',
            'app.repository_url' => 'https://github.com/kevin-belanger/lineup',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => 'Backup Admin',
            'is_student' => false,
            'is_teacher' => false,
        ]);

        DB::table('sessions')->insert([
            'id' => 'runtime-session-id',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Runtime Session Browser',
            'payload' => 'transient-session-payload',
            'last_activity' => 1,
        ]);

        DB::table('cache')->insert([
            'key' => 'runtime-cache-key',
            'value' => 'transient-cache-value',
            'expiration' => 1,
        ]);

        DB::table('cache_locks')->insert([
            'key' => 'runtime-cache-lock-key',
            'owner' => 'transient-cache-lock-owner',
            'expiration' => 1,
        ]);

        DB::table('jobs')->insert([
            'queue' => 'runtime-queue',
            'payload' => 'transient-job-payload',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 1,
            'created_at' => 1,
        ]);

        DB::table('job_batches')->insert([
            'id' => 'runtime-job-batch-id',
            'name' => 'Runtime job batch',
            'total_jobs' => 1,
            'pending_jobs' => 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => 1,
            'finished_at' => null,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'runtime-failed-job-uuid',
            'connection' => 'database',
            'queue' => 'runtime-failed-queue',
            'payload' => 'transient-failed-job-payload',
            'exception' => 'Runtime failed job exception',
            'failed_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.database.backup.download'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/sql; charset=UTF-8');

        $this->assertStringStartsWith('attachment; filename="lineup-database-backup-', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();

        $this->assertStringContainsString('-- LineUp database backup', $content);
        $this->assertStringContainsString('-- Application: LineUp', $content);
        $this->assertStringContainsString('-- App version: v0.0.3', $content);
        $this->assertStringContainsString('-- Repository: https://github.com/kevin-belanger/lineup', $content);
        $this->assertStringContainsString('-- Generated at:', $content);
        $this->assertStringContainsString('-- Database:', $content);
        $this->assertStringContainsString('CREATE TABLE `users`', $content);
        $this->assertStringContainsString('INSERT INTO `users`', $content);
        $this->assertStringContainsString('Backup Admin', $content);
        $this->assertStringContainsString('CREATE TABLE `sessions`', $content);
        $this->assertStringContainsString('-- Data for table `sessions` was excluded from this backup.', $content);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $content);
        $this->assertStringNotContainsString('transient-session-payload', $content);
        $this->assertStringNotContainsString('transient-cache-value', $content);
        $this->assertStringNotContainsString('transient-job-payload', $content);
        $this->assertStringNotContainsString('transient-failed-job-payload', $content);
    }

    public function test_non_admin_can_not_download_database_backup(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.database.backup.download'))
            ->assertForbidden();
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
