<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function edit(Request $request): View
    {
        return view('student.classroom', [
            'classrooms' => $this->availableClassrooms()
                ->with('openingHours')
                ->get(['id', 'name', 'description']),
            'currentClassroomId' => null,
        ]);
    }

    public function leave(Request $request): RedirectResponse
    {
        $activeRequests = $this->activeRequests($request)->get();

        if ($activeRequests->whereIn('status', SupportRequest::teacherActiveStatuses())->isNotEmpty()) {
            return back()->with('toast', [
                'type' => 'warning',
                'message' => __('You cannot leave this room because a request is being handled by a teacher.'),
            ]);
        }

        if ($activeRequests->isNotEmpty()) {
            $changedClassroomIds = $activeRequests->pluck('classroom_id')->filter()->unique();

            $this->activeRequests($request)->update([
                'status' => SupportRequest::STATUS_CANCELLED,
                'assigned_teacher_id' => null,
                'assigned_at' => null,
                'cancelled_by' => SupportRequest::CANCELLED_BY_STUDENT,
                'cancel_reason' => SupportRequest::CANCEL_REASON_CHANGED_CLASSROOM,
            ]);

            $changeMarker = app(SupportRequestChangeMarker::class);
            $changedClassroomIds->each(fn (int $classroomId) => $changeMarker->touch($classroomId));
        }

        $request->session()->forget('current_classroom_id');

        return redirect()->route('student.classroom.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('is_active', true)],
        ]);

        $newClassroomId = (int) $validated['classroom_id'];

        if (! $this->availableClassrooms()->whereKey($newClassroomId)->exists()) {
            return back()->withErrors([
                'classroom_id' => __('The selected room is not available.'),
            ])->withInput();
        }

        $request->session()->put('current_classroom_id', $newClassroomId);

        return redirect()->route('student.dashboard')->with('status', __('Current room updated.'));
    }

    private function activeRequests(Request $request)
    {
        return SupportRequest::query()
            ->where('student_id', $request->user()->id)
            ->whereIn('status', SupportRequest::activeStatuses());
    }

    private function availableClassrooms()
    {
        return Classroom::query()
            ->where('is_active', true)
            ->whereHas('subjects', fn ($query) => $query->where('is_active', true))
            ->orderBy('name');
    }
}
