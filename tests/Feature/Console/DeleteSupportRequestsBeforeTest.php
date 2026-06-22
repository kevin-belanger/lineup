<?php

namespace Tests\Feature\Console;

use App\Models\PersonalNote;
use App\Models\SupportRequest;
use App\Models\TeacherActiveRequestOrder;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeleteSupportRequestsBeforeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_requests_created_before_the_given_date(): void
    {
        app(ApplicationSettings::class)->updateTimezone('UTC');

        $teacher = User::factory()->teacher()->create();
        $oldRequest = SupportRequest::factory()->create([
            'assigned_teacher_id' => $teacher->id,
            'created_at' => Carbon::parse('2026-05-31 23:59:59', 'UTC'),
        ]);
        $sameDayRequest = SupportRequest::factory()->create([
            'created_at' => Carbon::parse('2026-06-01 00:00:00', 'UTC'),
        ]);
        $newRequest = SupportRequest::factory()->create([
            'created_at' => Carbon::parse('2026-06-02 12:00:00', 'UTC'),
        ]);
        $note = PersonalNote::factory()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $oldRequest->id,
        ]);
        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $oldRequest->id,
            'sort_order' => 1,
        ]);

        $this->artisan('requests:delete-before', ['date' => '2026-06-01'])
            ->expectsOutput('Deleted 1 request(s) created before 2026-06-01 00:00:00 UTC.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('support_requests', ['id' => $oldRequest->id]);
        $this->assertDatabaseHas('support_requests', ['id' => $sameDayRequest->id]);
        $this->assertDatabaseHas('support_requests', ['id' => $newRequest->id]);
        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'support_request_id' => $oldRequest->id,
        ]);
        $this->assertNull($note->refresh()->support_request_id);
    }

    public function test_dry_run_reports_matching_requests_without_deleting_them(): void
    {
        app(ApplicationSettings::class)->updateTimezone('UTC');

        $oldRequest = SupportRequest::factory()->create([
            'created_at' => Carbon::parse('2026-05-31 23:59:59', 'UTC'),
        ]);

        $this->artisan('requests:delete-before', [
            'date' => '2026-06-01',
            '--dry-run' => true,
        ])
            ->expectsOutput('Dry run complete. 1 request(s) created before 2026-06-01 00:00:00 UTC would be deleted.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('support_requests', ['id' => $oldRequest->id]);
    }

    public function test_it_rejects_invalid_dates(): void
    {
        $this->artisan('requests:delete-before', ['date' => '2026-99-99'])
            ->expectsOutput('Invalid date. Use the YYYY-MM-DD format.')
            ->assertExitCode(1);
    }
}
