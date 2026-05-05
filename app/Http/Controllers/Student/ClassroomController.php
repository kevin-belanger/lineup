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
            'classrooms' => Classroom::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'currentClassroomId' => $request->session()->get('current_classroom_id'),
            'activeRequests' => $this->activeRequests($request)->with(['classroom:id,name', 'subject:id,name'])->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')->where('is_active', true)],
            'confirm_cancel_active_requests' => ['nullable', 'boolean'],
        ]);

        $newClassroomId = (int) $validated['classroom_id'];
        $activeRequests = $this->activeRequests($request)->get();
        $hasActiveRequestsInAnotherClassroom = $activeRequests
            ->contains(fn (SupportRequest $supportRequest) => $supportRequest->classroom_id !== $newClassroomId);

        if ($hasActiveRequestsInAnotherClassroom && ! $request->boolean('confirm_cancel_active_requests')) {
            return back()->withErrors([
                'confirm_cancel_active_requests' => 'Confirme le changement de local pour annuler tes demandes en cours.',
            ])->withInput();
        }

        if ($hasActiveRequestsInAnotherClassroom) {
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

        $request->session()->put('current_classroom_id', $newClassroomId);

        return redirect()->route('student.dashboard')->with('status', 'Local courant mis a jour.');
    }

    private function activeRequests(Request $request)
    {
        return SupportRequest::query()
            ->where('student_id', $request->user()->id)
            ->whereIn('status', SupportRequest::activeStatuses());
    }
}
