<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardView extends Component
{
    public string $pageTitle = '';

    public function mount(ApplicationSettings $settings): void
    {
        $this->pageTitle = $this->buildPageTitle($settings);
    }

    #[On('teacher-requests-updated')]
    public function updatePageTitle(ApplicationSettings $settings): void
    {
        $this->pageTitle = $this->buildPageTitle($settings);

        $this->dispatch('teacher-page-title-updated', title: $this->pageTitle);
    }

    public function render(): View
    {
        return view('livewire.teacher.dashboard-view');
    }

    private function buildPageTitle(ApplicationSettings $settings): string
    {
        $baseTitle = $settings->displayName();
        $classroomId = $this->currentClassroomId();

        if ($classroomId === null) {
            return $baseTitle;
        }

        $waitingCount = SupportRequest::query()
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->count();

        $activeStudentName = SupportRequest::query()
            ->with('student:id,first_name,last_name,deleted_at')
            ->leftJoin('teacher_active_request_orders as active_request_orders', function ($join): void {
                $join
                    ->on('support_requests.id', '=', 'active_request_orders.support_request_id')
                    ->where('active_request_orders.teacher_id', '=', auth()->id());
            })
            ->select('support_requests.*')
            ->where('classroom_id', $classroomId)
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->whereNotNull('student_id')
            ->orderByRaw('COALESCE(active_request_orders.sort_order, 0) DESC')
            ->orderByDesc('support_requests.is_priority')
            ->orderByDesc('support_requests.assigned_at')
            ->orderByDesc('support_requests.created_at')
            ->orderByDesc('support_requests.id')
            ->first()
            ?->student
            ?->displayName();

        if ($activeStudentName !== null && $activeStudentName !== '') {
            return sprintf('(%d) - %s - %s', $waitingCount, $activeStudentName, $baseTitle);
        }

        return sprintf('(%d) - %s', $waitingCount, $baseTitle);
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }
}
