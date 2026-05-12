<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportRequestController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        if ($this->hasActiveRequest($request)) {
            return redirect()->route('student.dashboard')->with('toast', [
                'type' => 'info',
                'message' => __('You already have an active request.'),
            ]);
        }

        $classroom = $this->currentClassroom($request);

        if ($classroom === null) {
            return redirect()->route('student.classroom.edit')->with('toast', [
                'type' => 'info',
                'message' => __('Please choose a room before creating a request.'),
            ]);
        }

        return view('student.requests.form', [
            'supportRequest' => new SupportRequest([
                'type' => SupportRequest::TYPE_EXPLANATION,
                'status' => SupportRequest::STATUS_WAITING,
            ]),
            'classroom' => $classroom,
            'subjects' => $this->activeSubjects($classroom),
            'typeLabels' => SupportRequest::typeLabels(),
            'action' => route('student.requests.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->hasActiveRequest($request)) {
            return redirect()->route('student.dashboard')->with('toast', [
                'type' => 'info',
                'message' => __('You already have an active request.'),
            ]);
        }

        $classroom = $this->currentClassroom($request);

        if ($classroom === null) {
            return redirect()->route('student.classroom.edit')->with('toast', [
                'type' => 'info',
                'message' => __('Please choose a room before creating a request.'),
            ]);
        }

        SupportRequest::query()->create([
            ...$this->validatedData($request, $classroom),
            'student_id' => $request->user()->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_at' => null,
            'completed_at' => null,
        ]);

        app(SupportRequestChangeMarker::class)->touch($classroom->id);

        return redirect()->route('student.dashboard')->with('status', __('Request created.'));
    }

    public function edit(Request $request, SupportRequest $supportRequest): View
    {
        $this->authorizeStudentRequest($request, $supportRequest);
        abort_unless($supportRequest->status === SupportRequest::STATUS_WAITING, 403);

        if ($supportRequest->classroom === null) {
            abort(403, __('The room associated with this request no longer exists.'));
        }

        return view('student.requests.form', [
            'supportRequest' => $supportRequest,
            'classroom' => $supportRequest->classroom,
            'subjects' => $this->activeSubjects($supportRequest->classroom),
            'typeLabels' => SupportRequest::typeLabels(),
            'action' => route('student.requests.update', $supportRequest),
            'method' => 'PATCH',
        ]);
    }

    public function update(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorizeStudentRequest($request, $supportRequest);
        abort_unless($supportRequest->status === SupportRequest::STATUS_WAITING, 403);

        $supportRequest->update($this->validatedData($request, $supportRequest->classroom));
        app(SupportRequestChangeMarker::class)->touch($supportRequest->classroom_id);

        return redirect()->route('student.dashboard')->with('status', __('Request updated.'));
    }

    public function cancel(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $classroomId = $supportRequest->classroom_id;
        $updated = SupportRequest::query()
            ->whereKey($supportRequest->id)
            ->where('student_id', $request->user()->id)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->update([
                'status' => SupportRequest::STATUS_CANCELLED,
                'cancelled_by' => SupportRequest::CANCELLED_BY_STUDENT,
                'cancel_reason' => SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => __('The request has been updated.'),
            ]);
        }

        app(SupportRequestChangeMarker::class)->touch($classroomId);

        return back()->with('status', __('Request cancelled.'));
    }

    public function markReady(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorizeStudentRequest($request, $supportRequest);
        abort_unless($supportRequest->status === SupportRequest::STATUS_PAUSED, 403);

        $supportRequest->update([
            'status' => SupportRequest::STATUS_READY,
        ]);

        app(SupportRequestChangeMarker::class)->touch($supportRequest->classroom_id);

        return back()->with('status', __('The teacher will see that you are ready.'));
    }

    public function history(Request $request): View
    {
        return view('student.history', [
            'requests' => SupportRequest::query()
                ->with(['classroom:id,name', 'subject:id,name,url', 'assignedTeacher:id,name'])
                ->where('student_id', $request->user()->id)
                ->whereIn('status', SupportRequest::historyStatuses())
                ->latest()
                ->paginate(20),
            'statusLabels' => SupportRequest::statusLabels(),
            'typeLabels' => SupportRequest::typeLabels(),
        ]);
    }

    /**
     * @return array{subject_id: int, moodle_tile_number: int, table_number: string, type: string, comment: ?string}
     */
    private function validatedData(Request $request, Classroom $classroom): array
    {
        $validated = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')
                    ->where('is_active', true)
                    ->where('classroom_id', $classroom->id),
            ],
            'moodle_tile_number' => ['required', 'integer', 'min:1', 'max:9999'],
            'table_number' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', Rule::in(array_keys(SupportRequest::typeLabels()))],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'subject_id' => (int) $validated['subject_id'],
            'moodle_tile_number' => (int) $validated['moodle_tile_number'],
            'table_number' => $validated['table_number'],
            'type' => $validated['type'],
            'comment' => $validated['comment'] ?? null,
        ];
    }

    private function currentClassroom(Request $request): ?Classroom
    {
        $classroomId = $request->session()->get('current_classroom_id');

        if ($classroomId === null) {
            return null;
        }

        return Classroom::query()
            ->whereKey($classroomId)
            ->where('is_active', true)
            ->first();
    }

    private function activeSubjects(Classroom $classroom)
    {
        return Subject::query()
            ->where('classroom_id', $classroom->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function authorizeStudentRequest(Request $request, SupportRequest $supportRequest): void
    {
        abort_unless($supportRequest->student_id === $request->user()->id, 403);
    }

    private function hasActiveRequest(Request $request): bool
    {
        return SupportRequest::query()
            ->where('student_id', $request->user()->id)
            ->whereIn('status', SupportRequest::activeStatuses())
            ->exists();
    }
}
