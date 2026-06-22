<?php

namespace App\Livewire\Admin;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class RequestStatistics extends Component
{
    public string $period = 'last_30_days';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $month = null;

    /** @var array<int, string> */
    public array $classroomIds = [];

    public function mount(ApplicationSettings $settings): void
    {
        $today = CarbonImmutable::now($settings->timezone());

        $this->startDate = $today->subDays(29)->toDateString();
        $this->endDate = $today->toDateString();
        $this->month = $today->format('Y-m');
    }

    public function render(ApplicationSettings $settings): View
    {
        $timezone = $settings->timezone();
        [$start, $end] = $this->dateRange($timezone);
        $requests = $this->completedRequests($start, $end);
        $series = $this->dailySeries($requests, $start, $end, $timezone);
        $subjectRows = $this->subjectRows($requests);

        return view('livewire.admin.request-statistics', [
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'subjectRows' => $subjectRows,
            'totalRequests' => $requests->count(),
            'distinctStudents' => $requests->pluck('student_id')->filter()->unique()->count(),
            'waitStats' => $this->durationStats($requests->pluck('calculated_wait_time_minutes')->filter(fn ($value): bool => $value !== null)),
            'interventionStats' => $this->durationStats($requests->pluck('calculated_response_time_minutes')->filter(fn ($value): bool => $value !== null)),
            'chartData' => $series,
            'chartKey' => md5(json_encode($series, JSON_THROW_ON_ERROR)),
            'tileStatisticsKey' => md5(json_encode([
                'period' => $this->period,
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'month' => $this->month,
                'classroomIds' => $this->selectedClassroomIds(),
            ], JSON_THROW_ON_ERROR)),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(string $timezone): array
    {
        $today = CarbonImmutable::now($timezone);

        if ($this->period === 'month' && is_string($this->month) && preg_match('/^\d{4}-\d{2}$/', $this->month) === 1) {
            $month = CarbonImmutable::parse($this->month.'-01', $timezone);
            $this->syncDisplayedDateRange($month->startOfMonth(), $month->endOfMonth());

            return [
                $month->startOfMonth(),
                $month->endOfMonth(),
            ];
        }

        if ($this->period === 'custom') {
            $start = $this->parseDate($this->startDate, $timezone, $today)->startOfDay();
            $end = $this->parseDate($this->endDate, $timezone, $start)->endOfDay();

            if ($end->lessThan($start)) {
                $end = $start->endOfDay();
            }

            return [$start, $end];
        }

        $start = $today->subDays(29)->startOfDay();
        $end = $today->endOfDay();
        $this->syncDisplayedDateRange($start, $end);

        return [
            $start,
            $end,
        ];
    }

    private function syncDisplayedDateRange(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
    }

    private function parseDate(?string $date, string $timezone, CarbonImmutable $fallback): CarbonImmutable
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return $fallback;
        }

        return CarbonImmutable::parse($date, $timezone);
    }

    /**
     * @return Collection<int, SupportRequest>
     */
    private function completedRequests(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $query = SupportRequest::query()
            ->where('status', SupportRequest::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [
                $start->timezone(config('app.timezone')),
                $end->timezone(config('app.timezone')),
            ]);

        $classroomIds = $this->selectedClassroomIds();

        if ($classroomIds !== []) {
            $query->whereIn('classroom_id', $classroomIds);
        }

        return $query
            ->orderBy('completed_at')
            ->get([
                'id',
                'student_id',
                'classroom_id',
                'subject_id',
                'moodle_tile_number',
                'completed_at',
                'calculated_wait_time_minutes',
                'calculated_response_time_minutes',
            ]);
    }

    /**
     * @return array<int, int>
     */
    private function selectedClassroomIds(): array
    {
        return collect($this->classroomIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SupportRequest>  $requests
     * @return array<string, array<int, float|int|string|null>>
     */
    private function dailySeries(Collection $requests, CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
        $requestsByDay = $requests->groupBy(fn (SupportRequest $request): string => $request->completed_at->timezone($timezone)->toDateString());

        $labels = [];
        $requestCounts = [];
        $studentCounts = [];
        $waitAverages = [];
        $interventionAverages = [];

        foreach (CarbonPeriod::create($start->startOfDay(), '1 day', $end->startOfDay()) as $day) {
            $date = CarbonImmutable::instance($day)->timezone($timezone)->toDateString();
            $dayRequests = $requestsByDay->get($date, collect());

            $labels[] = $date;
            $requestCounts[] = $dayRequests->count();
            $studentCounts[] = $dayRequests->pluck('student_id')->filter()->unique()->count();
            $waitAverages[] = $this->averageOrNull($dayRequests->pluck('calculated_wait_time_minutes')->filter(fn ($value): bool => $value !== null));
            $interventionAverages[] = $this->averageOrNull($dayRequests->pluck('calculated_response_time_minutes')->filter(fn ($value): bool => $value !== null));
        }

        return [
            'labels' => $labels,
            'requestCounts' => $requestCounts,
            'studentCounts' => $studentCounts,
            'waitAverages' => $waitAverages,
            'interventionAverages' => $interventionAverages,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array{average: float|null, min: int|null, max: int|null}
     */
    private function durationStats(Collection $values): array
    {
        $values = $values->map(fn ($value): int => (int) $value)->values();

        if ($values->isEmpty()) {
            return [
                'average' => null,
                'min' => null,
                'max' => null,
            ];
        }

        return [
            'average' => round((float) $values->average(), 1),
            'min' => $values->min(),
            'max' => $values->max(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function averageOrNull(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->map(fn ($value): int => (int) $value)->average(), 1);
    }

    /**
     * @param  Collection<int, SupportRequest>  $requests
     * @return Collection<int, array{subject_id: int|null, subject_name: string, requests: int, distinct_students: int, percent: float, wait_average: float|null, intervention_average: float|null}>
     */
    private function subjectRows(Collection $requests): Collection
    {
        $subjectNames = Subject::query()
            ->whereIn('id', $requests->pluck('subject_id')->filter()->unique())
            ->pluck('name', 'id');
        $totalRequests = max($requests->count(), 1);

        return $requests
            ->groupBy(fn (SupportRequest $request): string => $request->subject_id === null ? 'none' : (string) $request->subject_id)
            ->map(function (Collection $subjectRequests, string $subjectKey) use ($subjectNames, $totalRequests): array {
                $subjectId = $subjectKey === 'none' ? null : (int) $subjectKey;

                return [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectId === null ? __('N/A') : ($subjectNames[$subjectId] ?? __('N/A')),
                    'requests' => $subjectRequests->count(),
                    'distinct_students' => $subjectRequests->pluck('student_id')->filter()->unique()->count(),
                    'percent' => round(($subjectRequests->count() / $totalRequests) * 100, 1),
                    'wait_average' => $this->averageOrNull($subjectRequests->pluck('calculated_wait_time_minutes')->filter(fn ($value): bool => $value !== null)),
                    'intervention_average' => $this->averageOrNull($subjectRequests->pluck('calculated_response_time_minutes')->filter(fn ($value): bool => $value !== null)),
                ];
            })
            ->sortBy([
                ['requests', 'desc'],
                ['subject_name', 'asc'],
            ])
            ->values();
    }
}
