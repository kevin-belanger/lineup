<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ApplicationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class RequestHistory extends Component
{
    use WithPagination;

    public string $period = 'today';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $teacherFilter = 'all';

    public string $search = '';

    public function mount(ApplicationSettings $settings): void
    {
        $today = CarbonImmutable::now($settings->timezone())->toDateString();

        $this->startDate = $today;
        $this->endDate = $today;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'startDate', 'endDate', 'teacherFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    #[On('teacher-requests-updated')]
    public function refreshRequests(): void
    {
        //
    }

    public function render(ApplicationSettings $settings): View
    {
        $query = $this->historyQuery($settings);

        return view('livewire.teacher.request-history', [
            'requests' => $query->paginate(12),
            'teacherOptions' => $this->teacherOptions(),
            'statusLabels' => SupportRequest::statusLabels(),
            'timezone' => $settings->timezone(),
        ]);
    }

    private function historyQuery(ApplicationSettings $settings): Builder
    {
        $query = SupportRequest::query()
            ->with([
                'student:id,first_name,last_name,deleted_at',
                'subject:id,name,url',
                'assignedTeacher:id,first_name,last_name,deleted_at',
                'priorityRequester:id,first_name,last_name,deleted_at',
            ])
            ->where('classroom_id', $this->currentClassroomId());

        $this->applyPeriodFilter($query, $settings->timezone());
        $this->applyTeacherFilter($query);
        $this->applySearchFilter($query);

        return $query
            ->latest('created_at')
            ->latest('id');
    }

    private function applyPeriodFilter(Builder $query, string $timezone): void
    {
        if ($this->period === 'all') {
            return;
        }

        if ($this->period === 'custom') {
            if ($this->startDate !== null && $this->startDate !== '') {
                $query->where('created_at', '>=', $this->localDateBoundary($this->startDate, $timezone, true));
            }

            if ($this->endDate !== null && $this->endDate !== '') {
                $query->where('created_at', '<=', $this->localDateBoundary($this->endDate, $timezone, false));
            }

            return;
        }

        $today = CarbonImmutable::now($timezone);

        $query->whereBetween('created_at', [
            $today->startOfDay()->timezone(config('app.timezone')),
            $today->endOfDay()->timezone(config('app.timezone')),
        ]);
    }

    private function applyTeacherFilter(Builder $query): void
    {
        if ($this->teacherFilter === 'all') {
            return;
        }

        if ($this->teacherFilter === 'mine') {
            $query->where('assigned_teacher_id', auth()->id());

            return;
        }

        if (ctype_digit($this->teacherFilter)) {
            $query->where('assigned_teacher_id', (int) $this->teacherFilter);
        }
    }

    private function applySearchFilter(Builder $query): void
    {
        $search = trim($this->search);

        if ($search === '') {
            return;
        }

        $matchingStatuses = $this->matchingLabelKeys(SupportRequest::statusLabels(), $search);
        $query->where(function (Builder $query) use ($search, $matchingStatuses): void {
            $query
                ->where('comment', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('request_type', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $query) => $this->applyUserNameSearch($query, $search))
                ->orWhereHas('subject', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('assignedTeacher', fn (Builder $query) => $this->applyUserNameSearch($query, $search))
                ->orWhereHas('priorityRequester', fn (Builder $query) => $this->applyUserNameSearch($query, $search));

            if ($matchingStatuses !== []) {
                $query->orWhereIn('status', $matchingStatuses);
            }
        });
    }

    private function localDateBoundary(string $date, string $timezone, bool $startOfDay): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = CarbonImmutable::now($timezone)->toDateString();
        }

        $date = CarbonImmutable::parse($date, $timezone);
        $date = $startOfDay ? $date->startOfDay() : $date->endOfDay();

        return $date->timezone(config('app.timezone'));
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    private function matchingLabelKeys(array $labels, string $search): array
    {
        $search = mb_strtolower($search);

        return collect($labels)
            ->filter(fn (string $label): bool => str_contains(mb_strtolower($label), $search))
            ->keys()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    private function teacherOptions(): Collection
    {
        $teacherIds = SupportRequest::query()
            ->where('classroom_id', $this->currentClassroomId())
            ->whereNotNull('assigned_teacher_id')
            ->distinct()
            ->pluck('assigned_teacher_id');

        return User::query()
            ->whereIn('id', $teacherIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->sortBy(fn (User $user): int => $user->id === auth()->id() ? 0 : 1)
            ->values();
    }

    private function applyUserNameSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $query) use ($search): void {
            $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%");

            foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $query->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%");
            }
        });
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }
}
