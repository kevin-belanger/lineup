<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
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
            $this->toast('warning', 'Cette demande a ete prise par un autre enseignant.');
            $this->dispatchRefresh();

            return;
        }

        $this->toast('success', 'Demande prise en charge.');
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        $this->dispatchRefresh();
    }

    public function assignAndComplete(int $supportRequestId): void
    {
        $classroomId = $this->currentClassroomId();
        $completedAt = now();

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
                'updated_at' => $completedAt,
            ]);

        if ($updated === 0) {
            $this->toast('warning', 'Cette demande ne peut plus etre prise en charge et terminee.');
            $this->dispatchRefresh();

            return;
        }

        $this->toast('success', 'Demande prise en charge et terminee.');
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
            $this->toast('info', 'Cette demande ne peut plus etre annulee depuis la file.');
            $this->dispatchRefresh();

            return;
        }

        $this->toast('success', 'Demande annulee.');
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
            'requests' => SupportRequest::query()
                ->with(['student:id,name', 'subject:id,name,url', 'priorityRequester:id,name'])
                ->where('classroom_id', $this->currentClassroomId())
                ->where('status', SupportRequest::STATUS_WAITING)
                ->orderByDesc('is_priority')
                ->oldest('created_at')
                ->get(),
            'typeLabels' => SupportRequest::typeLabels(),
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
