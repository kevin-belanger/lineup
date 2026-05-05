<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $activeRequest = SupportRequest::query()
            ->where('assigned_teacher_id', $request->user()->id)
            ->whereIn('status', SupportRequest::teacherActiveStatuses())
            ->latest()
            ->first(['classroom_id']);

        if ($activeRequest?->classroom_id !== null) {
            $request->session()->put('current_classroom_id', $activeRequest->classroom_id);
        }

        $currentClassroom = null;

        if ($request->session()->has('current_classroom_id')) {
            $currentClassroom = Classroom::query()
                ->whereKey($request->session()->get('current_classroom_id'))
                ->where('is_active', true)
                ->first();

            if ($currentClassroom === null) {
                $request->session()->forget('current_classroom_id');
            }
        }

        if ($currentClassroom === null) {
            return redirect()->route('teacher.classroom.edit');
        }

        return view('teacher.dashboard', [
            'currentClassroom' => $currentClassroom,
        ]);
    }
}
