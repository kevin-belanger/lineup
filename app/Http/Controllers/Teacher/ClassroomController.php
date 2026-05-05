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
        return view('teacher.classroom', [
            'classrooms' => Classroom::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'currentClassroomId' => $request->session()->get('current_classroom_id'),
            'activeRequests' => $this->assignedActiveRequests($request)->with(['classroom:id,name', 'subject:id,name'])->get(),
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
                'classroom_id' => 'Tu as encore des demandes en cours dans ton local actuel.',
            ]);
        }

        $request->session()->put('current_classroom_id', $newClassroomId);

        return redirect()->route('teacher.dashboard')->with('status', 'Local courant mis a jour.');
    }

    private function assignedActiveRequests(Request $request)
    {
        return SupportRequest::query()
            ->where('assigned_teacher_id', $request->user()->id)
            ->whereIn('status', SupportRequest::teacherActiveStatuses());
    }
}
