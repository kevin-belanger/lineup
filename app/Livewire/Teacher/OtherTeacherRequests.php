<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class OtherTeacherRequests extends Component
{
    public ?int $managingRequestId = null;

    public function openManagementModal(int $supportRequestId): void
    {
        if (! $this->manageableRequestQuery($supportRequestId)->exists()) {
            $this->toast('error', __('This request cannot be managed.'));
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        $this->managingRequestId = $supportRequestId;
    }

    public function closeManagementModal(): void
    {
        $this->managingRequestId = null;
    }

    public function requeue(): void
    {
        $this->updateManagedRequest([
            'assigned_teacher_id' => null,
            'assigned_at' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'updated_at' => now(),
        ], __('Request returned to waiting.'));
    }

    public function complete(): void
    {
        $this->updateManagedRequest([
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'updated_at' => now(),
        ], __('Request completed.'));
    }

    public function cancel(): void
    {
        $this->updateManagedRequest([
            'status' => SupportRequest::STATUS_CANCELLED,
            'cancelled_by' => SupportRequest::CANCELLED_BY_TEACHER,
            'cancel_reason' => SupportRequest::CANCEL_REASON_TEACHER_CANCELLED,
            'updated_at' => now(),
        ], __('Request cancelled.'));
    }

    #[On('teacher-requests-updated')]
    public function refreshRequests(): void
    {
        //
    }

    public function render(): View
    {
        return view('livewire.teacher.other-teacher-requests', [
            'requests' => SupportRequest::query()
                ->with(['student:id,name', 'subject:id,name,url', 'assignedTeacher:id,name', 'priorityRequester:id,name'])
                ->where('classroom_id', session('current_classroom_id'))
                ->whereNotNull('assigned_teacher_id')
                ->where('assigned_teacher_id', '!=', auth()->id())
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->oldest('created_at')
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
            'typeLabels' => SupportRequest::typeLabels(),
            'managedRequest' => $this->managedRequest(),
        ]);
    }

    private function updateManagedRequest(array $values, string $successMessage): void
    {
        $supportRequestId = $this->managingRequestId;

        if ($supportRequestId === null) {
            $this->toast('error', __('No request selected.'));

            return;
        }

        $classroomId = $this->currentClassroomId();

        $updated = $this->manageableRequestQuery($supportRequestId)->update($values);

        $this->managingRequestId = null;

        if ($updated === 0) {
            $this->toast('error', __('This request cannot be changed.'));
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        $this->toast('success', $successMessage);
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));
    }

    private function managedRequest(): ?SupportRequest
    {
        if ($this->managingRequestId === null) {
            return null;
        }

        return $this->manageableRequestQuery($this->managingRequestId)
            ->with(['student:id,name', 'subject:id,name,url', 'assignedTeacher:id,name'])
            ->first();
    }

    private function manageableRequestQuery(int $supportRequestId)
    {
        return SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $this->currentClassroomId())
            ->whereNotNull('assigned_teacher_id')
            ->where('assigned_teacher_id', '!=', auth()->id())
            ->where('is_priority', false)
            ->whereIn('status', SupportRequest::teacherActiveStatuses());
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
