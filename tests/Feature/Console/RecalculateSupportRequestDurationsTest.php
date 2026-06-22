<?php

namespace Tests\Feature\Console;

use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecalculateSupportRequestDurationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalculates_completed_non_cancelled_requests_using_current_opening_hours(): void
    {
        app(ApplicationSettings::class)->updateTimezone('UTC');

        $classroom = Classroom::factory()->create();
        ClassroomOpeningHour::factory()->create([
            'classroom_id' => $classroom->id,
            'days' => [1],
            'opens_at' => '10:00',
            'closes_at' => '11:00',
            'sort_order' => 0,
        ]);
        ClassroomOpeningHour::factory()->create([
            'classroom_id' => $classroom->id,
            'days' => [1],
            'opens_at' => '12:00',
            'closes_at' => '13:00',
            'sort_order' => 1,
        ]);

        $completed = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'created_at' => Carbon::parse('2026-06-15 10:55:00', 'UTC'),
            'assigned_at' => Carbon::parse('2026-06-15 12:15:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-06-15 12:30:00', 'UTC'),
            'calculated_wait_time_minutes' => 999,
            'calculated_response_time_minutes' => 999,
        ]);
        $originalUpdatedAt = $completed->updated_at;

        $cancelled = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_CANCELLED,
            'created_at' => Carbon::parse('2026-06-15 10:55:00', 'UTC'),
            'assigned_at' => Carbon::parse('2026-06-15 12:15:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-06-15 12:30:00', 'UTC'),
            'calculated_wait_time_minutes' => 777,
            'calculated_response_time_minutes' => 777,
        ]);

        $active = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'created_at' => Carbon::parse('2026-06-15 10:55:00', 'UTC'),
            'assigned_at' => Carbon::parse('2026-06-15 12:15:00', 'UTC'),
            'completed_at' => null,
            'calculated_wait_time_minutes' => 555,
            'calculated_response_time_minutes' => 555,
        ]);

        $this->artisan('requests:recalculate-durations')
            ->expectsOutput('Recalculated durations for 1 of 1 checked request(s).')
            ->assertExitCode(0);

        $completed->refresh();

        $this->assertSame(20, $completed->calculated_wait_time_minutes);
        $this->assertSame(15, $completed->calculated_response_time_minutes);
        $this->assertTrue($originalUpdatedAt->equalTo($completed->updated_at));
        $this->assertSame(777, $cancelled->refresh()->calculated_wait_time_minutes);
        $this->assertSame(777, $cancelled->calculated_response_time_minutes);
        $this->assertSame(555, $active->refresh()->calculated_wait_time_minutes);
        $this->assertSame(555, $active->calculated_response_time_minutes);
    }

    public function test_dry_run_reports_changes_without_writing_them(): void
    {
        app(ApplicationSettings::class)->updateTimezone('UTC');

        $classroom = Classroom::factory()->create();
        ClassroomOpeningHour::factory()->create([
            'classroom_id' => $classroom->id,
            'days' => [1],
            'opens_at' => '10:00',
            'closes_at' => '11:00',
        ]);

        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'created_at' => Carbon::parse('2026-06-15 09:30:00', 'UTC'),
            'assigned_at' => Carbon::parse('2026-06-15 10:15:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-06-15 10:45:00', 'UTC'),
            'calculated_wait_time_minutes' => 999,
            'calculated_response_time_minutes' => 999,
        ]);

        $this->artisan('requests:recalculate-durations', ['--dry-run' => true])
            ->expectsOutput('Dry run complete. 1 request(s) checked; 1 would be updated.')
            ->assertExitCode(0);

        $supportRequest->refresh();

        $this->assertSame(999, $supportRequest->calculated_wait_time_minutes);
        $this->assertSame(999, $supportRequest->calculated_response_time_minutes);
    }
}
