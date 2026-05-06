<?php

namespace Tests\Feature\Console;

use App\Models\Classroom;
use App\Models\Setting;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AutoCancelSupportRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_cancel_requests_when_setting_is_disabled(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->updateAutoCancelRequests(false, '16:30');

        $supportRequest = SupportRequest::factory()->create([
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $this->artisan('requests:auto-cancel', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->refresh()->status);
    }

    public function test_it_does_not_cancel_requests_before_configured_time(): void
    {
        $settings = app(ApplicationSettings::class);
        $settings->updateTimezone('America/Toronto');
        $settings->updateAutoCancelRequests(true, now()->addMinute()->format('H:i'));

        $supportRequest = SupportRequest::factory()->create([
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $this->artisan('requests:auto-cancel')
            ->assertExitCode(0);

        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->refresh()->status);
    }

    public function test_it_uses_configured_timezone_when_checking_configured_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 21:30:00', 'UTC'));

        try {
            $settings = app(ApplicationSettings::class);
            $settings->updateTimezone('America/Toronto');
            $settings->updateAutoCancelRequests(true, '16:30');

            $supportRequest = SupportRequest::factory()->create([
                'status' => SupportRequest::STATUS_WAITING,
            ]);

            $this->artisan('requests:auto-cancel')
                ->assertExitCode(0);

            $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->refresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_saved_timezone_falls_back_to_toronto(): void
    {
        Setting::query()->create([
            'key' => ApplicationSettings::TIMEZONE_KEY,
            'value' => 'Not/AZone',
        ]);

        $this->assertSame('America/Toronto', app(ApplicationSettings::class)->timezone());
    }

    public function test_it_cancels_active_requests_and_leaves_history_untouched(): void
    {
        Log::spy();

        $settings = app(ApplicationSettings::class);
        $settings->updateAutoCancelRequests(true, '16:30');

        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        $waiting = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);
        $assigned = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $paused = SupportRequest::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now(),
        ]);
        $ready = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_READY,
            'assigned_at' => now(),
        ]);
        $completed = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
        ]);
        $alreadyCancelled = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_CANCELLED,
            'cancelled_by' => SupportRequest::CANCELLED_BY_STUDENT,
            'cancel_reason' => SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED,
        ]);

        $this->artisan('requests:auto-cancel', ['--force' => true])
            ->assertExitCode(0);

        foreach ([$waiting, $assigned, $paused, $ready] as $supportRequest) {
            $supportRequest->refresh();

            $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->status);
            $this->assertSame(SupportRequest::CANCELLED_BY_SYSTEM, $supportRequest->cancelled_by);
            $this->assertSame(SupportRequest::CANCEL_REASON_END_OF_DAY, $supportRequest->cancel_reason);
        }

        $this->assertSame(SupportRequest::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_CANCELLED, $alreadyCancelled->refresh()->status);
        $this->assertSame(SupportRequest::CANCELLED_BY_STUDENT, $alreadyCancelled->cancelled_by);
        $this->assertSame(SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED, $alreadyCancelled->cancel_reason);

        $changeMarker = app(SupportRequestChangeMarker::class);

        $this->assertSame(1, $changeMarker->current($classroom->id));
        $this->assertSame(1, $changeMarker->current($otherClassroom->id));
        Log::shouldHaveReceived('info')->once();
    }
}
