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
            ->with('student:id,first_name,last_name')
            ->where('classroom_id', $classroomId)
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->whereNotNull('student_id')
            ->orderByDesc('is_priority')
            ->orderByDesc('assigned_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first()
            ?->student
            ?->fullName();

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
