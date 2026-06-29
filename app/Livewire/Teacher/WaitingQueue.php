<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use App\Services\SupportRequestDurationCalculator;
use App\Services\TeacherActiveRequestOrdering;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class WaitingQueue extends Component
{
    public ?int $confirmingCancellationId = null;

    public function assign(int $supportRequestId): void
    {
        $classroomId = $this->currentClassroomId();
        $assignedAt = now();

        $updated = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->whereNull('assigned_teacher_id')
            ->update([
                'assigned_teacher_id' => auth()->id(),
                'assigned_at' => $assignedAt,
                'status' => SupportRequest::STATUS_ASSIGNED,
                'updated_at' => $assignedAt,
            ]);

        if ($updated === 0) {
            $this->toast('warning', __('This request was taken by another teacher.'));
            $this->dispatchRefresh();

            return;
        }

        $ordering = app(TeacherActiveRequestOrdering::class);

        if (auth()->user()->place_new_requests_on_top) {
            $ordering->moveToTop(auth()->id(), $supportRequestId);
        } else {
            $ordering->moveToBottom(auth()->id(), $supportRequestId);
        }

        $this->toast('success', __('Request taken.'));
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        $this->dispatchRefresh();
    }

    public function assignAndComplete(int $supportRequestId): void
    {
        $classroomId = $this->currentClassroomId();
        $completedAt = now();
        $supportRequest = SupportRequest::query()
            ->with('classroom.openingHours')
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->whereNull('assigned_teacher_id')
            ->where('is_priority', false)
            ->first();

        if ($supportRequest === null) {
            $this->toast('warning', __('This request can no longer be taken and completed.'));
            $this->dispatchRefresh();

            return;
        }

        $updated = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->whereNull('assigned_teacher_id')
            ->where('is_priority', false)
            ->update([
                'assigned_teacher_id' => auth()->id(),
                'assigned_at' => $completedAt,
                'status' => SupportRequest::STATUS_COMPLETED,
                'completed_at' => $completedAt,
                ...app(SupportRequestDurationCalculator::class)->completionDurations($supportRequest, $completedAt),
                'calculated_response_time_minutes' => 0,
                'updated_at' => $completedAt,
            ]);

        if ($updated === 0) {
            $this->toast('warning', __('This request can no longer be taken and completed.'));
            $this->dispatchRefresh();

            return;
        }

        $this->toast('success', __('Request taken and completed.'));
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        $this->dispatchRefresh();
    }

    public function confirmCancel(int $supportRequestId): void
    {
        $this->confirmingCancellationId = $supportRequestId;
    }

    public function dismissCancel(): void
    {
        $this->confirmingCancellationId = null;
    }

    public function cancel(): void
    {
        $classroomId = $this->currentClassroomId();

        $updated = SupportRequest::query()
            ->whereKey($this->confirmingCancellationId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->where('is_priority', false)
            ->update([
                'assigned_teacher_id' => null,
                'assigned_at' => null,
                'status' => SupportRequest::STATUS_CANCELLED,
                'cancelled_by' => SupportRequest::CANCELLED_BY_TEACHER,
                'cancel_reason' => SupportRequest::CANCEL_REASON_TEACHER_CANCELLED,
                'updated_at' => now(),
            ]);

        $this->confirmingCancellationId = null;

        if ($updated === 0) {
            $this->toast('info', __('This request can no longer be cancelled from the queue.'));
            $this->dispatchRefresh();

            return;
        }

        $this->toast('success', __('Request cancelled.'));
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        $this->dispatchRefresh();
    }

    #[On('teacher-requests-updated')]
    public function refreshRequests(): void
    {
        //
    }

    public function render(): View
    {
        return view('livewire.teacher.waiting-queue', [
            'classroom' => Classroom::query()
                ->with('openingHours')
                ->find($this->currentClassroomId()),
            'requests' => SupportRequest::query()
                ->with(['student:id,first_name,last_name,deleted_at', 'subject:id,name,url', 'fieldAnswers', 'priorityRequester:id,first_name,last_name,deleted_at'])
                ->where('classroom_id', $this->currentClassroomId())
                ->where('status', SupportRequest::STATUS_WAITING)
                ->orderByDesc('is_priority')
                ->oldest('created_at')
                ->get(),
        ]);
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }

    private function dispatchRefresh(): void
    {
        DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
