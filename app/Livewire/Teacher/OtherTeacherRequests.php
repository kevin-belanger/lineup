<?php

namespace App\Livewire\Teacher;

use App\Models\SupportRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class OtherTeacherRequests extends Component
{
    #[On('teacher-requests-updated')]
    public function refreshRequests(): void
    {
        //
    }

    public function render(): View
    {
        return view('livewire.teacher.other-teacher-requests', [
            'requests' => SupportRequest::query()
                ->with(['student:id,name', 'subject:id,name', 'assignedTeacher:id,name'])
                ->where('classroom_id', session('current_classroom_id'))
                ->whereNotNull('assigned_teacher_id')
                ->where('assigned_teacher_id', '!=', auth()->id())
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->oldest('created_at')
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
        ]);
    }
}
