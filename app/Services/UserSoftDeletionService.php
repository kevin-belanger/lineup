<?php

namespace App\Services;

use App\Models\SupportRequest;
use App\Models\TeacherActiveRequestOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSoftDeletionService
{
    public function __construct(
        private readonly SupportRequestChangeMarker $changeMarker,
    ) {}

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $classroomIds = SupportRequest::query()
                ->where('assigned_teacher_id', $user->id)
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->whereNotNull('classroom_id')
                ->pluck('classroom_id')
                ->unique()
                ->all();

            SupportRequest::query()
                ->where('assigned_teacher_id', $user->id)
                ->whereIn('status', SupportRequest::teacherActiveStatuses())
                ->update([
                    'status' => SupportRequest::STATUS_WAITING,
                    'assigned_teacher_id' => null,
                    'assigned_at' => null,
                    'updated_at' => now(),
                ]);

            TeacherActiveRequestOrder::query()
                ->where('teacher_id', $user->id)
                ->delete();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            $user->forceFill([
                'email' => null,
                'email_verified_at' => null,
                'remember_token' => null,
                'is_active' => false,
            ])->save();

            $user->delete();

            foreach ($classroomIds as $classroomId) {
                $this->changeMarker->touch((int) $classroomId);
            }
        });
    }
}
