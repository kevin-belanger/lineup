<?php

namespace App\Console\Commands;

use App\Models\SupportRequest;
use App\Services\SupportRequestDurationCalculator;
use Illuminate\Console\Command;

class RecalculateSupportRequestDurations extends Command
{
    protected $signature = 'requests:recalculate-durations {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Recalculate support request durations from the current classroom opening hours.';

    public function handle(SupportRequestDurationCalculator $calculator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processedCount = 0;
        $changedCount = 0;

        SupportRequest::query()
            ->with('classroom.openingHours')
            ->where('status', '!=', SupportRequest::STATUS_CANCELLED)
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->chunkById(100, function ($supportRequests) use ($calculator, $dryRun, &$processedCount, &$changedCount): void {
                foreach ($supportRequests as $supportRequest) {
                    $processedCount++;

                    $durations = $calculator->completionDurations($supportRequest, $supportRequest->completed_at);

                    $supportRequest->forceFill($durations);

                    if (! $supportRequest->isDirty(['calculated_wait_time_minutes', 'calculated_response_time_minutes'])) {
                        continue;
                    }

                    $changedCount++;

                    if ($dryRun) {
                        continue;
                    }

                    $supportRequest->timestamps = false;
                    $supportRequest->saveQuietly();
                }
            });

        if ($dryRun) {
            $this->info("Dry run complete. {$processedCount} request(s) checked; {$changedCount} would be updated.");

            return self::SUCCESS;
        }

        $this->info("Recalculated durations for {$changedCount} of {$processedCount} checked request(s).");

        return self::SUCCESS;
    }
}
