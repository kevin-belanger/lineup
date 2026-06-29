<?php

namespace App\Livewire\Admin;

use App\Models\Subject;
use App\Models\SubjectRequestField;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class RequestTileStatistics extends Component
{
    public string $period = 'last_30_days';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $month = null;

    /** @var array<int, string> */
    public array $classroomIds = [];

    public string $selectedSubjectId = '';

    public string $selectedRequestFieldId = '';

    public function updatedSelectedSubjectId(): void
    {
        $this->selectedRequestFieldId = '';
    }

    public function render(ApplicationSettings $settings): View
    {
        [$start, $end] = $this->dateRange($settings->timezone());
        $requests = $this->completedRequests($start, $end);
        $subjectOptions = $this->subjectRows($requests);

        return view('livewire.admin.request-tile-statistics', [
            'subjectOptions' => $subjectOptions,
            'requestFieldOptions' => $this->requestFieldOptions(),
            'fieldValueRows' => $this->fieldValueRows($requests),
            'selectedSubjectName' => $this->selectedSubjectName($subjectOptions),
            'selectedRequestFieldName' => $this->selectedRequestFieldName(),
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

        return [
            $today->subDays(29)->startOfDay(),
            $today->endOfDay(),
        ];
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
                'completed_at',
                'calculated_wait_time_minutes',
                'calculated_response_time_minutes',
            ])
            ->load('fieldAnswers');
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
     * @return Collection<int, array{subject_id: int|null, subject_name: string}>
     */
    private function subjectRows(Collection $requests): Collection
    {
        $subjectNames = Subject::query()
            ->whereIn('id', $requests->pluck('subject_id')->filter()->unique())
            ->pluck('name', 'id');

        return $requests
            ->groupBy(fn (SupportRequest $request): string => $request->subject_id === null ? 'none' : (string) $request->subject_id)
            ->map(function (Collection $subjectRequests, string $subjectKey) use ($subjectNames): array {
                $subjectId = $subjectKey === 'none' ? null : (int) $subjectKey;

                return [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectId === null ? __('N/A') : ($subjectNames[$subjectId] ?? __('N/A')),
                    'requests' => $subjectRequests->count(),
                ];
            })
            ->sortBy([
                ['requests', 'desc'],
                ['subject_name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, SupportRequest>  $requests
     * @return Collection<int, array{field_id: int, field_name: string}>
     */
    private function requestFieldOptions(): Collection
    {
        if (! ctype_digit($this->selectedSubjectId)) {
            return collect();
        }

        return SubjectRequestField::query()
            ->where('subject_id', (int) $this->selectedSubjectId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SubjectRequestField $field): array => [
                'field_id' => $field->id,
                'field_name' => $field->name,
            ]);
    }

    /**
     * @param  Collection<int, SupportRequest>  $requests
     * @return Collection<int, array{value: string, requests: int, distinct_students: int, percent: float, wait_average: float|null, intervention_average: float|null}>
     */
    private function fieldValueRows(Collection $requests): Collection
    {
        if (! ctype_digit($this->selectedSubjectId) || ! ctype_digit($this->selectedRequestFieldId)) {
            return collect();
        }

        $fieldId = (int) $this->selectedRequestFieldId;
        $subjectRequests = $requests->where('subject_id', (int) $this->selectedSubjectId);
        $totalRequests = max($subjectRequests->count(), 1);

        return $subjectRequests
            ->groupBy(function (SupportRequest $request) use ($fieldId): string {
                $value = $request->fieldAnswers->firstWhere('subject_request_field_id', $fieldId)?->value;
                $value = trim((string) $value);

                return $value === '' ? 'none' : $value;
            })
            ->map(fn (Collection $fieldRequests, string $value): array => [
                'value' => $value === 'none' ? __('N/A') : $value,
                'requests' => $fieldRequests->count(),
                'distinct_students' => $fieldRequests->pluck('student_id')->filter()->unique()->count(),
                'percent' => round(($fieldRequests->count() / $totalRequests) * 100, 1),
                'wait_average' => $this->averageOrNull($fieldRequests->pluck('calculated_wait_time_minutes')->filter(fn ($value): bool => $value !== null)),
                'intervention_average' => $this->averageOrNull($fieldRequests->pluck('calculated_response_time_minutes')->filter(fn ($value): bool => $value !== null)),
            ])
            ->sortBy([
                ['requests', 'desc'],
                ['value', 'asc'],
            ])
            ->values();
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
     * @param  Collection<int, array{subject_id: int|null, subject_name: string}>  $subjectOptions
     */
    private function selectedSubjectName(Collection $subjectOptions): ?string
    {
        if (! ctype_digit($this->selectedSubjectId)) {
            return null;
        }

        return $subjectOptions->firstWhere('subject_id', (int) $this->selectedSubjectId)['subject_name'] ?? null;
    }

    private function selectedRequestFieldName(): ?string
    {
        if (! ctype_digit($this->selectedRequestFieldId)) {
            return null;
        }

        return SubjectRequestField::query()
            ->whereKey((int) $this->selectedRequestFieldId)
            ->value('name');
    }
}
