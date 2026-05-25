<?php

namespace App\Livewire\Student;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use App\Services\TeacherActiveRequestOrdering;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActiveRequests extends Component
{
    public ?int $confirmingAssignedCancellationId = null;

    public function cancel(int $supportRequestId): void
    {
        $supportRequest = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('student_id', auth()->id())
            ->first(['id', 'classroom_id']);

        $updated = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('student_id', auth()->id())
            ->where('status', SupportRequest::STATUS_WAITING)
            ->update([
                'status' => SupportRequest::STATUS_CANCELLED,
                'cancelled_by' => SupportRequest::CANCELLED_BY_STUDENT,
                'cancel_reason' => SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED,
                'updated_at' => now(),
            ]);

        $this->toast($updated === 1 ? 'success' : 'info', $updated === 1 ? __('Request cancelled.') : __('The request has been updated.'));

        if ($updated === 1) {
            app(SupportRequestChangeMarker::class)->touch($supportRequest?->classroom_id);
        }
    }

    public function confirmAssignedCancellation(int $supportRequestId): void
    {
        $this->confirmingAssignedCancellationId = $supportRequestId;
    }

    public function dismissAssignedCancellation(): void
    {
        $this->confirmingAssignedCancellationId = null;
    }

    public function cancelAssignedRequest(): void
    {
        $supportRequest = SupportRequest::query()
            ->whereKey($this->confirmingAssignedCancellationId)
            ->where('student_id', auth()->id())
            ->first(['id', 'classroom_id', 'assigned_teacher_id']);

        $updated = SupportRequest::query()
            ->whereKey($this->confirmingAssignedCancellationId)
            ->where('student_id', auth()->id())
            ->whereNotNull('assigned_teacher_id')
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->update([
                'status' => SupportRequest::STATUS_CANCELLED,
                'cancelled_by' => SupportRequest::CANCELLED_BY_STUDENT,
                'cancel_reason' => SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED,
                'updated_at' => now(),
            ]);

        $this->confirmingAssignedCancellationId = null;
        $this->toast($updated === 1 ? 'success' : 'info', $updated === 1 ? __('Request cancelled.') : __('The request has been updated.'));

        if ($updated === 1) {
            if ($supportRequest?->assigned_teacher_id !== null) {
                app(TeacherActiveRequestOrdering::class)->remove($supportRequest->assigned_teacher_id, $supportRequest->id);
            }

            app(SupportRequestChangeMarker::class)->touch($supportRequest?->classroom_id);
        }
    }

    public function render(): View
    {
        return view('livewire.student.active-requests', [
            'requests' => SupportRequest::query()
                ->with(['classroom:id,name', 'subject:id,name,url', 'assignedTeacher:id,first_name,last_name'])
                ->where('student_id', auth()->id())
                ->whereIn('status', SupportRequest::activeStatuses())
                ->latest()
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
            'typeLabels' => SupportRequest::typeLabels(),
        ]);
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
