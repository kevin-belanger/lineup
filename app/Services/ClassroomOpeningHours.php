<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

class ClassroomOpeningHours
{
    public function __construct(
        private readonly ApplicationSettings $settings,
    ) {}

    public function isOpen(Classroom $classroom, ?CarbonInterface $at = null): bool
    {
        $classroom->loadMissing('openingHours');

        if ($classroom->openingHours->isEmpty()) {
            return true;
        }

        $at = $at
            ? CarbonImmutable::instance($at)->timezone($this->settings->timezone())
            : CarbonImmutable::now($this->settings->timezone());

        $day = $at->dayOfWeekIso;
        $time = $at->format('H:i');

        return $classroom->openingHours->contains(function ($openingHour) use ($day, $time): bool {
            $days = collect($openingHour->days ?? [])->map(fn ($value): int => (int) $value);

            return $days->contains($day)
                && $time >= substr((string) $openingHour->opens_at, 0, 5)
                && $time < substr((string) $openingHour->closes_at, 0, 5);
        });
    }

    public function hasSchedule(Classroom $classroom): bool
    {
        $classroom->loadMissing('openingHours');

        return $classroom->openingHours->isNotEmpty();
    }

    public function dayLabels(array $days): string
    {
        $dayNumbers = collect($days)
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => array_key_exists($day, ClassroomOpeningHour::DAYS))
            ->unique()
            ->sort()
            ->values();

        if ($dayNumbers->all() === [1, 2, 3, 4, 5, 6, 7]) {
            return __('Every day');
        }

        if ($dayNumbers->all() === [1, 2, 3, 4, 5]) {
            return __('Monday to Friday');
        }

        return $dayNumbers
            ->map(fn (int $day): string => __(ClassroomOpeningHour::DAYS[$day]))
            ->implode(', ');
    }
}
