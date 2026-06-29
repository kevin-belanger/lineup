<?php

namespace App\Services;

use App\Models\SupportRequest;
use App\Models\User;

class TeacherPageTitle
{
    public function __construct(private readonly ApplicationSettings $settings)
    {
    }

    public function forClassroom(?int $classroomId, ?User $teacher): string
    {
        $baseTitle = $this->settings->displayName();

        if ($classroomId === null || $teacher === null) {
            return $baseTitle;
        }

        $waitingCount = SupportRequest::query()
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->count();

        $activeStudentName = SupportRequest::query()
            ->with('student:id,first_name,last_name,deleted_at')
            ->leftJoin('teacher_active_request_orders as active_request_orders', function ($join) use ($teacher): void {
                $join
                    ->on('support_requests.id', '=', 'active_request_orders.support_request_id')
                    ->where('active_request_orders.teacher_id', '=', $teacher->id);
            })
            ->select('support_requests.*')
            ->where('classroom_id', $classroomId)
            ->where('assigned_teacher_id', $teacher->id)
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
}
