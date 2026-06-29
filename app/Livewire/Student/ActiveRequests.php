<?php

namespace App\Livewire\Student;

use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ActiveRequests extends Component
{
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
        $exists = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('student_id', auth()->id())
            ->whereNotNull('assigned_teacher_id')
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->exists();

        $this->toast('info', $exists
            ? __('You cannot cancel a request while it is being taken by a teacher.')
            : __('The request has been updated.'));
    }

    public function refreshPageTitle(ApplicationSettings $settings): void
    {
        $title = $this->buildPageTitle($settings);

        $this->dispatch('page-title-updated', title: $title);
        $this->dispatch('teacher-page-title-updated', title: $title);
    }

    public function render(): View
    {
        return view('livewire.student.active-requests', [
            'requests' => SupportRequest::query()
                ->with(['classroom:id,name', 'subject:id,name,url', 'fieldAnswers', 'assignedTeacher:id,first_name,last_name,deleted_at'])
                ->where('student_id', auth()->id())
                ->whereIn('status', SupportRequest::activeStatuses())
                ->latest()
                ->get(),
            'statusLabels' => SupportRequest::statusLabels(),
        ]);
    }

    private function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    private function buildPageTitle(ApplicationSettings $settings): string
    {
        $baseTitle = $settings->displayName();
        $supportRequest = SupportRequest::query()
            ->with('assignedTeacher:id,first_name,last_name,deleted_at')
            ->where('student_id', auth()->id())
            ->whereIn('status', SupportRequest::activeStatuses())
            ->orderByRaw('CASE WHEN assigned_teacher_id IS NOT NULL THEN 0 ELSE 1 END')
            ->latest()
            ->first();

        if ($supportRequest === null) {
            return $baseTitle;
        }

        if ($supportRequest->assigned_teacher_id !== null) {
            return __('Taken by :name', ['name' => $supportRequest->assignedTeacherDisplayName()]).' - '.$baseTitle;
        }

        return (SupportRequest::statusLabels()[$supportRequest->status] ?? $supportRequest->status).' - '.$baseTitle;
    }
}
