<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class MyRequests extends Component
{
    public int $refreshKey = 0;

    public function complete(int $supportRequestId): void
    {
        $this->updateAssignedRequest($supportRequestId, [
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'updated_at' => now(),
        ], 'Demande terminee.');
    }

    public function pause(int $supportRequestId): void
    {
        $this->updateAssignedRequest($supportRequestId, [
            'status' => SupportRequest::STATUS_PAUSED,
            'updated_at' => now(),
        ], 'Demande mise en pause.', false);
    }

    public function unassign(int $supportRequestId): void
    {
        $this->updateAssignedRequest($supportRequestId, [
            'assigned_teacher_id' => null,
            'assigned_at' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'updated_at' => now(),
        ], 'Demande remise dans la file.', false);
    }

    #[On('teacher-requests-updated')]
    public function refreshRequests(): void
    {
        $this->refreshKey++;
    }

    public function render(): View
    {
        return view('livewire.teacher.my-requests', [
            'requests' => SupportRequest::query()
                ->with(['student:id,name', 'subject:id,name,url', 'priorityRequester:id,name'])
                ->where('classroom_id', $this->currentClassroomId())
                ->where('assigned_teacher_id', auth()->id())
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->orderByDesc('is_priority')
                ->orderByDesc('assigned_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
            'typeLabels' => SupportRequest::typeLabels(),
        ]);
    }

    private function updateAssignedRequest(int $supportRequestId, array $values, string $successMessage, bool $allowPriority = true): void
    {
        $classroomId = $this->currentClassroomId();

        $query = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::teacherActiveStatuses());

        if (! $allowPriority) {
            $query->where('is_priority', false);
        }

        $updated = $query->update($values);

        if ($updated === 0) {
            $this->toast('error', 'Cette demande ne peut pas etre modifiee.');
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        $this->refreshKey++;
        $this->toast('success', $successMessage);
        app(SupportRequestChangeMarker::class)->touch($classroomId);
        DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));
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
