<?php

namespace App\Services;

use App\Models\SupportRequest;
use App\Models\User;

class CompletedSupportRequestRestorer
{
    public function restoreAsAssigned(int $supportRequestId, ?int $classroomId, User $teacher, bool $requireAssignedToTeacher = false): bool
    {
        if (! $teacher->is_teacher) {
            return false;
        }

        $query = SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_COMPLETED);

        if ($requireAssignedToTeacher) {
            $query->where('assigned_teacher_id', $teacher->id);
        }

        return $query->update([
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
            'completed_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'updated_at' => now(),
        ]) === 1;
    }

    public function restoreAsWaiting(int $supportRequestId, ?int $classroomId, User $teacher): bool
    {
        if (! $teacher->is_teacher) {
            return false;
        }

        return SupportRequest::query()
            ->whereKey($supportRequestId)
            ->where('classroom_id', $classroomId)
            ->where('status', SupportRequest::STATUS_COMPLETED)
            ->update([
                'assigned_teacher_id' => null,
                'assigned_at' => null,
                'status' => SupportRequest::STATUS_WAITING,
                'completed_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
                'updated_at' => now(),
            ]) === 1;
    }
}
