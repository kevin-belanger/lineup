<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\RequestType;
use App\Models\Subject;
use App\Models\SubjectRequestField;
use App\Models\SupportRequest;
use App\Services\ApplicationSettings;
use App\Services\ClassroomOpeningHours;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        if (! app(ClassroomOpeningHours::class)->isOpen($classroom)) {
            return redirect()->route('student.dashboard')->with('toast', [
                'type' => 'warning',
                'message' => __('Unable to create a request right now because the room is closed.'),
            ]);
        }

        return view('student.requests.form', [
            'supportRequest' => new SupportRequest([
                'status' => SupportRequest::STATUS_WAITING,
            ]),
            'classroom' => $classroom,
            'subjects' => $this->activeSubjects($classroom),
            'requestFieldValues' => old('request_fields', []),
            'requestTypes' => $this->requestTypes(),
            'requestTypeRequired' => $this->requestTypeRequired(),
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

        if (! app(ClassroomOpeningHours::class)->isOpen($classroom)) {
            return redirect()->route('student.dashboard')->with('toast', [
                'type' => 'warning',
                'message' => __('Unable to create a request right now because the room is closed.'),
            ]);
        }

        [$data, $fieldAnswers] = $this->validatedData($request, $classroom);

        $supportRequest = SupportRequest::query()->create([
            ...$data,
            'student_id' => $request->user()->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_at' => null,
            'completed_at' => null,
        ]);

        $this->syncFieldAnswers($supportRequest, $fieldAnswers);

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
            'requestFieldValues' => old('request_fields', $supportRequest->fieldAnswers->mapWithKeys(fn ($answer): array => [
                $answer->subject_request_field_id => $answer->value,
            ])->all()),
            'requestTypes' => $this->requestTypes(),
            'requestTypeRequired' => $this->requestTypeRequired(),
            'action' => route('student.requests.update', $supportRequest),
            'method' => 'PATCH',
        ]);
    }

    public function update(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $this->authorizeStudentRequest($request, $supportRequest);
        abort_unless($supportRequest->status === SupportRequest::STATUS_WAITING, 403);

        [$data, $fieldAnswers] = $this->validatedData($request, $supportRequest->classroom, $supportRequest);

        $supportRequest->update($data);
        $this->syncFieldAnswers($supportRequest, $fieldAnswers);
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
                ->with(['classroom:id,name', 'subject:id,name,url', 'fieldAnswers', 'assignedTeacher:id,first_name,last_name,deleted_at'])
                ->where('student_id', $request->user()->id)
                ->whereIn('status', SupportRequest::historyStatuses())
                ->latest()
                ->paginate(20),
            'statusLabels' => SupportRequest::statusLabels(),
        ]);
    }

    /**
     * @return array{0: array{subject_id: int, moodle_tile_number: ?int, table_number: string, type: string, request_type: ?string, comment: ?string}, 1: array<int, array{field: SubjectRequestField, value: string}>}
     */
    private function validatedData(Request $request, Classroom $classroom, ?SupportRequest $supportRequest = null): array
    {
        $requestTypes = $this->requestTypes();
        $requestTypeRequired = $requestTypes->isNotEmpty() && $this->requestTypeRequired();

        $validated = $request->validate([
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('is_active', true),
            ],
            'table_number' => ['required', 'string', 'max:50'],
            'request_type_id' => [
                $requestTypeRequired ? 'required' : 'nullable',
                'integer',
                Rule::exists('request_types', 'id'),
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = Subject::query()
            ->with('activeRequestFields')
            ->whereKey((int) $validated['subject_id'])
            ->where('is_active', true)
            ->whereHas('locals', fn ($query) => $query->whereKey($classroom->id))
            ->first();

        if ($subject === null) {
            throw ValidationException::withMessages([
                'subject_id' => __('The selected subject is not available in this room.'),
            ]);
        }

        $fieldAnswers = $this->validatedFieldAnswers($request, $subject);

        $requestType = null;

        if ($requestTypes->isNotEmpty() && isset($validated['request_type_id'])) {
            $requestType = $requestTypes
                ->firstWhere('id', (int) $validated['request_type_id'])
                ?->name;
        } elseif ($supportRequest !== null) {
            $requestType = $supportRequest->request_type;
        }

        return [[
            'subject_id' => (int) $validated['subject_id'],
            'moodle_tile_number' => $this->legacyMoodleTileNumber($fieldAnswers),
            'table_number' => $validated['table_number'],
            'type' => $requestType ?? '',
            'request_type' => $requestType,
            'comment' => $validated['comment'] ?? null,
        ], $fieldAnswers->all()];
    }

    /**
     * @return Collection<int, array{field: SubjectRequestField, value: string}>
     */
    private function validatedFieldAnswers(Request $request, Subject $subject)
    {
        $rules = [];
        $attributes = [];

        foreach ($subject->activeRequestFields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];

            if ($field->type === SubjectRequestField::TYPE_INTEGER) {
                $fieldRules[] = 'integer';
            } elseif ($field->type === SubjectRequestField::TYPE_DECIMAL) {
                $fieldRules[] = 'numeric';
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
            }

            $rules["request_fields.{$field->id}"] = $fieldRules;
            $attributes["request_fields.{$field->id}"] = $field->name;
        }

        $validated = Validator::make($request->all(), $rules, [], $attributes)->validate();
        $values = $validated['request_fields'] ?? [];

        return $subject->activeRequestFields
            ->map(function (SubjectRequestField $field) use ($values): ?array {
                $value = trim((string) ($values[$field->id] ?? ''));

                if ($value === '') {
                    return null;
                }

                return [
                    'field' => $field,
                    'value' => $value,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array{field: SubjectRequestField, value: string}>  $fieldAnswers
     */
    private function legacyMoodleTileNumber($fieldAnswers): ?int
    {
        $answer = $fieldAnswers->first(fn (array $answer): bool => $answer['field']->key === SubjectRequestField::keyForName('Tuile Moodle'));

        if ($answer === null || ! ctype_digit((string) $answer['value'])) {
            return null;
        }

        return (int) $answer['value'];
    }

    /**
     * @param  array<int, array{field: SubjectRequestField, value: string}>  $fieldAnswers
     */
    private function syncFieldAnswers(SupportRequest $supportRequest, array $fieldAnswers): void
    {
        $supportRequest->fieldAnswers()->delete();

        foreach ($fieldAnswers as $answer) {
            $field = $answer['field'];

            $supportRequest->fieldAnswers()->create([
                'subject_request_field_id' => $field->id,
                'field_name' => $field->name,
                'field_key' => $field->key,
                'field_type' => $field->type,
                'value' => $answer['value'],
                'sort_order' => $field->sort_order,
            ]);
        }
    }

    private function currentClassroom(Request $request): ?Classroom
    {
        $classroomId = $request->session()->get('current_classroom_id');

        if ($classroomId === null) {
            return null;
        }

        return Classroom::query()
            ->with('openingHours')
            ->whereKey($classroomId)
            ->where('is_active', true)
            ->first();
    }

    private function activeSubjects(Classroom $classroom)
    {
        return $classroom->subjects()
            ->where('subjects.is_active', true)
            ->with('activeRequestFields')
            ->orderBy('subjects.name')
            ->get(['subjects.id', 'subjects.name']);
    }

    private function requestTypes()
    {
        return RequestType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function requestTypeRequired(): bool
    {
        return app(ApplicationSettings::class)->requestTypeRequired();
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
