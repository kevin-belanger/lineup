<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use App\Services\TeacherActiveRequestOrdering;
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
        ], __('Request completed.'));
    }

    public function pause(int $supportRequestId): void
    {
        $this->updateAssignedRequest($supportRequestId, [
            'status' => SupportRequest::STATUS_PAUSED,
            'updated_at' => now(),
        ], __('Request paused.'), false);
    }

    public function unassign(int $supportRequestId): void
    {
        $this->updateAssignedRequest($supportRequestId, [
            'assigned_teacher_id' => null,
            'assigned_at' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'updated_at' => now(),
        ], __('Request returned to the queue.'), false);
    }

    /**
     * @param  array<int, int|string>  $supportRequestIds
     */
    public function reorderRequests(array $supportRequestIds, TeacherActiveRequestOrdering $ordering): void
    {
        $this->persistActiveRequestOrder($supportRequestIds, $ordering);
    }

    public function moveRequestToPosition(int|string $supportRequestId, int $position): void
    {
        $orderedIds = $this->currentActiveRequestIds();
        $supportRequestId = (int) $supportRequestId;
        $currentIndex = array_search($supportRequestId, $orderedIds, true);

        if ($currentIndex === false) {
            return;
        }

        array_splice($orderedIds, $currentIndex, 1);
        array_splice($orderedIds, max(0, min($position, count($orderedIds))), 0, [$supportRequestId]);

        $this->persistActiveRequestOrder($orderedIds, app(TeacherActiveRequestOrdering::class));
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
                ->with(['student:id,first_name,last_name', 'subject:id,name,url', 'priorityRequester:id,first_name,last_name'])
                ->leftJoin('teacher_active_request_orders as active_request_orders', function ($join): void {
                    $join
                        ->on('support_requests.id', '=', 'active_request_orders.support_request_id')
                        ->where('active_request_orders.teacher_id', '=', auth()->id());
                })
                ->select('support_requests.*')
                ->where('classroom_id', $this->currentClassroomId())
                ->where('assigned_teacher_id', auth()->id())
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->orderByRaw('COALESCE(active_request_orders.sort_order, 0) DESC')
                ->orderByDesc('support_requests.is_priority')
                ->orderByDesc('support_requests.assigned_at')
                ->orderByDesc('support_requests.created_at')
                ->orderByDesc('support_requests.id')
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
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
            $this->toast('error', __('This request cannot be changed.'));
            DB::afterCommit(fn () => $this->dispatch('teacher-requests-updated'));

            return;
        }

        if ($this->requestLeavesActiveSection($values)) {
            app(TeacherActiveRequestOrdering::class)->remove(auth()->id(), $supportRequestId);
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

    /**
     * @return array<int, int>
     */
    private function currentActiveRequestIds(): array
    {
        return SupportRequest::query()
            ->leftJoin('teacher_active_request_orders as active_request_orders', function ($join): void {
                $join
                    ->on('support_requests.id', '=', 'active_request_orders.support_request_id')
                    ->where('active_request_orders.teacher_id', '=', auth()->id());
            })
            ->select('support_requests.id')
            ->where('classroom_id', $this->currentClassroomId())
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->orderByRaw('COALESCE(active_request_orders.sort_order, 0) DESC')
            ->orderByDesc('support_requests.is_priority')
            ->orderByDesc('support_requests.assigned_at')
            ->orderByDesc('support_requests.created_at')
            ->orderByDesc('support_requests.id')
            ->pluck('support_requests.id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int|string>  $supportRequestIds
     */
    private function persistActiveRequestOrder(array $supportRequestIds, TeacherActiveRequestOrdering $ordering): void
    {
        $ordering->reorder(auth()->id(), $supportRequestIds);
        $this->refreshKey++;
        app(SupportRequestChangeMarker::class)->touch($this->currentClassroomId());
        $this->dispatch('teacher-requests-updated');
    }

    private function requestLeavesActiveSection(array $values): bool
    {
        if (array_key_exists('assigned_teacher_id', $values) && $values['assigned_teacher_id'] !== auth()->id()) {
            return true;
        }

        return array_key_exists('status', $values)
            && ! in_array($values['status'], SupportRequest::teacherActiveStatuses(), true);
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }
}
