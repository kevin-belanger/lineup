<?php

namespace App\Console\Commands;

use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DeleteSupportRequestsBefore extends Command
{
    protected $signature = 'requests:delete-before {date : Delete requests created before this date (YYYY-MM-DD)} {--dry-run : Preview the deletion without writing to the database}';

    protected $description = 'Delete support requests created before a given date.';

    public function handle(ApplicationSettings $settings): int
    {
        $date = (string) $this->argument('date');
        $cutoff = $this->cutoffFromDate($date, $settings->timezone());

        if ($cutoff === null) {
            $this->error('Invalid date. Use the YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $query = SupportRequest::query()
            ->where('created_at', '<', $cutoff->utc());

        $count = (clone $query)->count();
        $localCutoff = $cutoff->format('Y-m-d H:i:s T');

        if ($this->option('dry-run')) {
            $this->info("Dry run complete. {$count} request(s) created before {$localCutoff} would be deleted.");

            return self::SUCCESS;
        }

        $deletedCount = $query->delete();

        $this->info("Deleted {$deletedCount} request(s) created before {$localCutoff}.");

        return self::SUCCESS;
    }

    private function cutoffFromDate(string $date, string $timezone): ?CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $parsed->startOfDay();
    }
}
