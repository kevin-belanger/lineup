<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

    public function nextOpeningAt(Classroom $classroom, ?CarbonInterface $at = null): ?CarbonImmutable
    {
        $classroom->loadMissing('openingHours');

        if ($classroom->openingHours->isEmpty()) {
            return null;
        }

        $timezone = $this->settings->timezone();
        $at = $at
            ? CarbonImmutable::instance($at)->timezone($timezone)
            : CarbonImmutable::now($timezone);

        for ($date = $at->startOfDay(); $date->lessThanOrEqualTo($at->addDays(7)->startOfDay()); $date = $date->addDay()) {
            foreach ($this->periodsForDay($classroom->openingHours, $date->dayOfWeekIso) as $period) {
                $opensAt = CarbonImmutable::parse($date->toDateString().' '.$period['opens_at'], $timezone);

                if ($opensAt->greaterThan($at)) {
                    return $opensAt;
                }
            }
        }

        return null;
    }

    public function closedUntilLabel(Classroom $classroom, ?CarbonInterface $at = null): ?string
    {
        $nextOpeningAt = $this->nextOpeningAt($classroom, $at);

        if ($nextOpeningAt === null) {
            return null;
        }

        $timezone = $this->settings->timezone();
        $at = $at
            ? CarbonImmutable::instance($at)->timezone($timezone)
            : CarbonImmutable::now($timezone);
        $time = $nextOpeningAt->format('H:i');

        if ($nextOpeningAt->isSameDay($at)) {
            return $time;
        }

        if ($nextOpeningAt->isSameDay($at->addDay())) {
            return __('Tomorrow at :time', ['time' => $time]);
        }

        return __(':day at :time', [
            'day' => __(ClassroomOpeningHour::DAYS[$nextOpeningAt->dayOfWeekIso] ?? $nextOpeningAt->isoFormat('dddd')),
            'time' => $time,
        ]);
    }

    public function openMinutesBetween(Classroom $classroom, CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $classroom->loadMissing('openingHours');

        if ($classroom->openingHours->isEmpty()) {
            return intdiv((int) $start->diffInSeconds($end, true), 60);
        }

        $timezone = $this->settings->timezone();
        $start = CarbonImmutable::instance($start)->timezone($timezone);
        $end = CarbonImmutable::instance($end)->timezone($timezone);
        $seconds = 0;

        for ($date = $start->startOfDay(); $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $day = $date->dayOfWeekIso;

            foreach ($this->periodsForDay($classroom->openingHours, $day) as $period) {
                $opensAt = CarbonImmutable::parse($date->toDateString().' '.$period['opens_at'], $timezone);
                $closesAt = CarbonImmutable::parse($date->toDateString().' '.$period['closes_at'], $timezone);
                $overlapStart = $opensAt->greaterThan($start) ? $opensAt : $start;
                $overlapEnd = $closesAt->lessThan($end) ? $closesAt : $end;

                if ($overlapEnd->greaterThan($overlapStart)) {
                    $seconds += (int) $overlapStart->diffInSeconds($overlapEnd, false);
                }
            }
        }

        return intdiv($seconds, 60);
    }

    public function liveDurationSchedule(Classroom $classroom): array
    {
        $classroom->loadMissing('openingHours');

        return [
            'timezone' => $this->settings->timezone(),
            'periods' => $classroom->openingHours
                ->map(fn (ClassroomOpeningHour $openingHour): array => [
                    'days' => collect($openingHour->days ?? [])->map(fn ($day): int => (int) $day)->values()->all(),
                    'opens_at' => substr((string) $openingHour->opens_at, 0, 5),
                    'closes_at' => substr((string) $openingHour->closes_at, 0, 5),
                ])
                ->values()
                ->all(),
        ];
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

    private function periodsForDay(Collection $openingHours, int $day): array
    {
        return $openingHours
            ->filter(fn (ClassroomOpeningHour $openingHour): bool => collect($openingHour->days ?? [])
                ->map(fn ($value): int => (int) $value)
                ->contains($day))
            ->map(fn (ClassroomOpeningHour $openingHour): array => [
                'opens_at' => substr((string) $openingHour->opens_at, 0, 5),
                'closes_at' => substr((string) $openingHour->closes_at, 0, 5),
            ])
            ->sortBy('opens_at')
            ->values()
            ->all();
    }
}
