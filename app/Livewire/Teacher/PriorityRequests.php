<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PriorityRequests extends Component
{
    #[Validate('required|exists:classrooms,id')]
    public ?int $classroomId = null;

    #[Validate('required|string|max:500')]
    public string $message = '';

    /** @var array<int, int> */
    public array $trackedVersions = [];

    public int $formResetKey = 0;

    public function mount(ApplicationSettings $settings, SupportRequestChangeMarker $changeMarker): void
    {
        $this->message = $this->defaultMessage($settings);
        $this->trackedVersions = $this->priorityRequestVersions($changeMarker);
    }

    public function create(): void
    {
        $validated = $this->validate();

        $classroom = Classroom::query()
            ->whereKey($validated['classroomId'])
            ->where('is_active', true)
            ->first();

        if ($classroom === null) {
            $this->toast('error', __('The selected room is not available.'));

            return;
        }

        SupportRequest::query()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => null,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => auth()->id(),
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'comment' => trim($validated['message']),
            'assigned_at' => null,
            'completed_at' => null,
        ]);

        $this->reset('classroomId');
        $this->message = $this->defaultMessage(app(ApplicationSettings::class));
        $this->formResetKey++;
        $this->toast('success', __('Priority request sent.'));
        app(SupportRequestChangeMarker::class)->touch($classroom->id);
        $this->trackedVersions = $this->priorityRequestVersions(app(SupportRequestChangeMarker::class));
        DB::afterCommit(fn () => $this->dispatchPriorityRefresh());
    }

    public function cancel(int $supportRequestId): void
    {
        $this->updateOwnPriorityRequest($supportRequestId, [
            'status' => SupportRequest::STATUS_CANCELLED,
            'cancelled_by' => SupportRequest::CANCELLED_BY_TEACHER,
            'cancel_reason' => SupportRequest::CANCEL_REASON_TEACHER_CANCELLED,
            'updated_at' => now(),
        ], __('Priority request cancelled.'));
    }

    public function complete(int $supportRequestId): void
    {
        $this->updateOwnPriorityRequest($supportRequestId, [
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'updated_at' => now(),
        ], __('Priority request completed.'));
    }

    public function checkForPriorityRequestChanges(SupportRequestChangeMarker $changeMarker): void
    {
        $versions = $this->priorityRequestVersions($changeMarker);

        if ($versions === $this->trackedVersions) {
            return;
        }

        $this->trackedVersions = $versions;
    }

    #[On('teacher-requests-updated')]
    public function refreshRequests(SupportRequestChangeMarker $changeMarker): void
    {
        $this->trackedVersions = $this->priorityRequestVersions($changeMarker);
    }

    public function render(): View
    {
        return view('livewire.teacher.priority-requests', [
            'classrooms' => Classroom::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'requests' => SupportRequest::query()
                ->with(['classroom:id,name', 'assignedTeacher:id,first_name,last_name'])
                ->where('is_priority', true)
                ->where('priority_requested_by_teacher_id', auth()->id())
                ->whereIn('status', SupportRequest::activeStatuses())
                ->oldest('created_at')
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
        ]);
    }

    private function updateOwnPriorityRequest(int $supportRequestId, array $values, string $successMessage): void
    {
        $supportRequest = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('is_priority', true)
            ->where('priority_requested_by_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::activeStatuses())
            ->first(['id', 'classroom_id']);

        if ($supportRequest === null) {
            $this->toast('info', __('This priority request can no longer be changed.'));
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        $updated = SupportRequest::query()
            ->whereKey($supportRequest->id)
            ->where('is_priority', true)
            ->where('priority_requested_by_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::activeStatuses())
            ->update($values);

        if ($updated === 0) {
            $this->toast('info', __('This priority request can no longer be changed.'));
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        $this->toast('success', $successMessage);
        app(SupportRequestChangeMarker::class)->touch($supportRequest->classroom_id);
        $this->trackedVersions = $this->priorityRequestVersions(app(SupportRequestChangeMarker::class));
        DB::afterCommit(fn () => $this->dispatchPriorityRefresh());
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    private function defaultMessage(ApplicationSettings $settings): string
    {
        return $settings->priorityRequestDefaultMessage();
    }

    /**
     * @return array<int, int>
     */
    private function priorityRequestVersions(SupportRequestChangeMarker $changeMarker): array
    {
        return SupportRequest::query()
            ->where('is_priority', true)
            ->where('priority_requested_by_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::activeStatuses())
            ->whereNotNull('classroom_id')
            ->pluck('classroom_id')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $classroomId): array => [$classroomId => $changeMarker->current($classroomId)])
            ->all();
    }

    private function dispatchPriorityRefresh(): void
    {
        $this->dispatch('teacher-requests-updated');
        $this->dispatch('$refresh');
    }
}
