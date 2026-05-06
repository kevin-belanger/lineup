<?php

namespace App\Console\Commands;

use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCancelSupportRequests extends Command
{
    protected $signature = 'requests:auto-cancel {--force : Execute immediately without checking the configured time}';

    protected $description = 'Cancel active support requests at the configured end-of-day time.';

    public function handle(ApplicationSettings $settings, SupportRequestChangeMarker $changeMarker): int
    {
        if (! $settings->autoCancelRequestsEnabled()) {
            $this->info('Automatic request cancellation is disabled.');

            return self::SUCCESS;
        }

        $configuredTime = $settings->autoCancelRequestsTime();
        $timezone = $settings->timezone();
        $currentTime = now($timezone)->format('H:i');

        if (! $this->option('force') && $currentTime !== $configuredTime) {
            $this->info("Automatic request cancellation is scheduled for {$configuredTime} {$timezone}; current time is {$currentTime}.");

            return self::SUCCESS;
        }

        $activeQuery = SupportRequest::query()
            ->whereIn('status', SupportRequest::activeStatuses());

        $classroomIds = (clone $activeQuery)
            ->pluck('classroom_id')
            ->filter()
            ->unique()
            ->values();

        $cancelledCount = $activeQuery->update([
            'status' => SupportRequest::STATUS_CANCELLED,
            'cancelled_by' => SupportRequest::CANCELLED_BY_SYSTEM,
            'cancel_reason' => SupportRequest::CANCEL_REASON_END_OF_DAY,
            'updated_at' => now(),
        ]);

        $classroomIds->each(fn (int $classroomId) => $changeMarker->touch($classroomId));

        Log::info('Automatically cancelled active support requests.', [
            'count' => $cancelledCount,
            'time' => $configuredTime,
            'timezone' => $timezone,
        ]);

        $this->info("Automatically cancelled {$cancelledCount} active request(s).");

        return self::SUCCESS;
    }
}
