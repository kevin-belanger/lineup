<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function edit(Request $request): View
    {
        $activeRequests = $this->assignedActiveRequests($request)
            ->with(['classroom:id,name,requires_table_number', 'subject:id,name,url'])
            ->get();

        $request->session()->forget('current_classroom_id');

        return view('teacher.classroom', [
            'classrooms' => Classroom::query()
                ->where('is_active', true)
                ->with('openingHours')
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'currentClassroomId' => null,
            'activeRequests' => $activeRequests,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('is_active', true)],
        ]);

        $newClassroomId = (int) $validated['classroom_id'];
        $hasActiveRequestInAnotherClassroom = $this->assignedActiveRequests($request)
            ->where('classroom_id', '!=', $newClassroomId)
            ->exists();

        if ($hasActiveRequestInAnotherClassroom) {
            return back()->withErrors([
                'classroom_id' => __('There are still active requests in the current room.'),
            ]);
        }

        $request->session()->put('current_classroom_id', $newClassroomId);

        return redirect()->route('teacher.dashboard')->with('status', __('Current room updated.'));
    }

    private function assignedActiveRequests(Request $request)
    {
        return SupportRequest::query()
            ->where('assigned_teacher_id', $request->user()->id)
            ->whereIn('status', SupportRequest::teacherActiveStatuses());
    }
}
