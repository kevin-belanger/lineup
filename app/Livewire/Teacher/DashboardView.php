<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Services\TeacherPageTitle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardView extends Component
{
    public string $pageTitle = '';

    public function mount(TeacherPageTitle $title): void
    {
        $this->pageTitle = $this->buildPageTitle($title);
    }

    #[On('teacher-requests-updated')]
    public function updatePageTitle(TeacherPageTitle $title): void
    {
        $this->pageTitle = $this->buildPageTitle($title);

        $this->dispatch('teacher-page-title-updated', title: $this->pageTitle);
    }

    public function render(): View
    {
        return view('livewire.teacher.dashboard-view', [
            'currentClassroom' => $this->currentClassroom(),
        ]);
    }

    private function buildPageTitle(TeacherPageTitle $title): string
    {
        return $title->forClassroom($this->currentClassroomId(), auth()->user());
    }

    private function currentClassroomId(): ?int
    {
        return session('current_classroom_id');
    }

    private function currentClassroom(): ?Classroom
    {
        $classroomId = $this->currentClassroomId();

        if ($classroomId === null) {
            return null;
        }

        return Classroom::query()
            ->with('openingHours')
            ->find($classroomId);
    }
}
