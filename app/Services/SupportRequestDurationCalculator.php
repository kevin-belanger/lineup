<?php

namespace App\Services;

use App\Models\SupportRequest;
use Carbon\CarbonInterface;

class SupportRequestDurationCalculator
{
    public function __construct(
        private readonly ClassroomOpeningHours $openingHours,
    ) {}

    /**
     * @return array{calculated_wait_time_minutes: int, calculated_response_time_minutes: ?int}
     */
    public function completionDurations(SupportRequest $supportRequest, CarbonInterface $completedAt): array
    {
        $supportRequest->loadMissing('classroom.openingHours');

        if ($supportRequest->classroom === null) {
            return [
                'calculated_wait_time_minutes' => $this->rawMinutes($supportRequest->created_at, $supportRequest->assigned_at ?? $completedAt),
                'calculated_response_time_minutes' => $supportRequest->assigned_at
                    ? $this->rawMinutes($supportRequest->assigned_at, $completedAt)
                    : null,
            ];
        }

        return [
            'calculated_wait_time_minutes' => $this->openingHours->openMinutesBetween(
                $supportRequest->classroom,
                $supportRequest->created_at,
                $supportRequest->assigned_at ?? $completedAt,
            ),
            'calculated_response_time_minutes' => $supportRequest->assigned_at
                ? $this->openingHours->openMinutesBetween($supportRequest->classroom, $supportRequest->assigned_at, $completedAt)
                : null,
        ];
    }

    private function rawMinutes(CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return intdiv((int) $start->diffInSeconds($end, true), 60);
    }
}
