<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\ActiveRequests;
use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use App\Models\RequestType;
use App\Models\Subject;
use App\Models\SubjectRequestField;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StudentSupportRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_choose_current_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        $response = $this->actingAs($student)->put(route('student.classroom.update'), [
            'classroom_id' => $classroom->id,
        ]);

        $response
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('current_classroom_id', $classroom->id);
    }

    public function test_student_dashboard_redirects_to_classroom_choice_when_no_classroom_is_selected(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response->assertRedirect(route('student.classroom.edit'));
    }

    public function test_student_classroom_choice_shows_student_breadcrumb(): void
    {
        $student = User::factory()->create();

        $this
            ->actingAs($student)
            ->get(route('student.classroom.edit'))
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSee('Student');
    }

    public function test_student_classroom_choice_does_not_preselect_current_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.classroom.edit'))
            ->assertOk()
            ->assertSee('name="classroom_id"', false)
            ->assertDontSee('checked="checked"', false);
    }

    public function test_student_classroom_choice_shows_only_active_classrooms_with_active_subjects(): void
    {
        $student = User::factory()->create();
        $availableClassroom = Classroom::factory()->create([
            'name' => 'Available room',
            'is_active' => true,
        ]);
        $noSubjectClassroom = Classroom::factory()->create([
            'name' => 'No subject room',
            'is_active' => true,
        ]);
        $inactiveSubjectClassroom = Classroom::factory()->create([
            'name' => 'Inactive subject room',
            'is_active' => true,
        ]);
        $inactiveClassroom = Classroom::factory()->create([
            'name' => 'Inactive room',
            'is_active' => false,
        ]);

        Subject::factory()->create([
            'classroom_id' => $availableClassroom->id,
            'is_active' => true,
        ]);
        Subject::factory()->create([
            'classroom_id' => $inactiveSubjectClassroom->id,
            'is_active' => false,
        ]);
        Subject::factory()->create([
            'classroom_id' => $inactiveClassroom->id,
            'is_active' => true,
        ]);

        $this
            ->actingAs($student)
            ->get(route('student.classroom.edit'))
            ->assertOk()
            ->assertSeeText($availableClassroom->name)
            ->assertDontSeeText($noSubjectClassroom->name)
            ->assertDontSeeText($inactiveSubjectClassroom->name)
            ->assertDontSeeText($inactiveClassroom->name);
    }

    public function test_student_classroom_choice_shows_empty_state_when_no_classroom_is_available(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'name' => 'Unavailable room',
            'is_active' => true,
        ]);

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'is_active' => false,
        ]);

        $this
            ->actingAs($student)
            ->get(route('student.classroom.edit'))
            ->assertOk()
            ->assertSeeText('No room is currently available.')
            ->assertDontSeeText($classroom->name)
            ->assertDontSeeText('Use this room');
    }

    public function test_student_can_not_choose_classroom_without_active_subjects(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'is_active' => true,
        ]);

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($student)->put(route('student.classroom.update'), [
            'classroom_id' => $classroom->id,
        ]);

        $response->assertSessionHasErrors('classroom_id');
        $this->assertFalse(session()->has('current_classroom_id'));
    }

    public function test_student_dashboard_shows_current_classroom_breadcrumb(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Student')
            ->assertSee($classroom->name)
            ->assertSee(route('student.classroom.leave'), false);
    }

    public function test_student_dashboard_shows_when_current_classroom_opens_next(): void
    {
        Carbon::setTestNow('2026-06-22 07:30:00');

        $student = User::factory()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 203']);
        ClassroomOpeningHour::factory()->create([
            'classroom_id' => $classroom->id,
            'days' => [1],
            'opens_at' => '08:00',
            'closes_at' => '16:00',
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Local 203')
            ->assertSee('Room closed until 08:00')
            ->assertSee('data-closed-until-template', false)
            ->assertSee('data-classroom-opening-status-text', false)
            ->assertDontSee('data-classroom-opening-status-dot', false);
    }

    public function test_student_dashboard_shows_confirmation_before_leaving_room_with_waiting_request(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSeeText('Do you want to cancel your active request and change rooms?')
            ->assertSee(route('student.classroom.leave'), false);

        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->refresh()->status);
    }

    public function test_student_leaving_room_cancels_waiting_requests_and_clears_current_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.classroom.leave'));

        $response
            ->assertRedirect(route('student.classroom.edit'))
            ->assertSessionMissing('current_classroom_id');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->status);
        $this->assertNull($supportRequest->assigned_teacher_id);
        $this->assertNull($supportRequest->assigned_at);
    }

    public function test_student_cannot_leave_room_when_request_is_handled_by_teacher(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->paused()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->from(route('student.dashboard'))
            ->post(route('student.classroom.leave'));

        $response
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('current_classroom_id', $classroom->id)
            ->assertSessionHas('toast', [
                'type' => 'warning',
                'message' => 'You cannot leave this room because a request is being handled by a teacher.',
            ]);

        $this->assertSame(SupportRequest::STATUS_PAUSED, $supportRequest->refresh()->status);
    }

    public function test_student_can_create_support_request_with_current_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $moodleTileField = SubjectRequestField::factory()->required()->integer()->create([
            'subject_id' => $subject->id,
            'name' => 'Tuile Moodle',
            'key' => SubjectRequestField::keyForName('Tuile Moodle'),
        ]);
        $requestType = RequestType::factory()->create([
            'name' => 'Validation',
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'request_fields' => [
                    $moodleTileField->id => 42,
                ],
                'table_number' => '8',
                'request_type_id' => $requestType->id,
                'comment' => 'Je veux valider mon exercice.',
            ]);

        $response->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('support_requests', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'moodle_tile_number' => 42,
            'table_number' => '8',
            'request_type' => 'Validation',
            'status' => SupportRequest::STATUS_WAITING,
        ]);
        $this->assertDatabaseHas('support_request_field_answers', [
            'subject_request_field_id' => $moodleTileField->id,
            'field_name' => 'Tuile Moodle',
            'value' => '42',
        ]);
    }

    public function test_student_request_form_hides_table_number_when_room_does_not_require_it(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'requires_table_number' => false,
        ]);
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.requests.create'))
            ->assertOk()
            ->assertDontSee('name="table_number"', false)
            ->assertDontSeeText('Table number');
    }

    public function test_student_can_create_support_request_without_table_number_when_room_does_not_require_it(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'requires_table_number' => false,
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('support_requests', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'table_number' => null,
            'status' => SupportRequest::STATUS_WAITING,
        ]);
    }

    public function test_student_must_provide_table_number_when_room_requires_it(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create([
            'requires_table_number' => true,
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
            ])
            ->assertSessionHasErrors('table_number');

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_student_dynamic_request_fields_are_validated_and_saved(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $chapterField = SubjectRequestField::factory()->required()->integer()->create([
            'subject_id' => $subject->id,
            'name' => 'Chapitre',
            'key' => SubjectRequestField::keyForName('Chapitre'),
        ]);
        $durationField = SubjectRequestField::factory()->required()->decimal()->create([
            'subject_id' => $subject->id,
            'name' => 'Duree',
            'key' => SubjectRequestField::keyForName('Duree'),
        ]);
        $topicField = SubjectRequestField::factory()->create([
            'subject_id' => $subject->id,
            'name' => 'Sujet',
            'key' => SubjectRequestField::keyForName('Sujet'),
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'request_fields' => [
                    $chapterField->id => '2.5',
                    $durationField->id => 'abc',
                ],
                'table_number' => '8',
            ])
            ->assertSessionHasErrors([
                "request_fields.{$chapterField->id}",
                "request_fields.{$durationField->id}",
            ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'request_fields' => [
                    $chapterField->id => '2',
                    $durationField->id => '1.5',
                    $topicField->id => 'Equations',
                ],
                'table_number' => '8',
            ])
            ->assertRedirect(route('student.dashboard'));

        $supportRequest = SupportRequest::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertNull($supportRequest->moodle_tile_number);
        $this->assertDatabaseHas('support_request_field_answers', [
            'support_request_id' => $supportRequest->id,
            'subject_request_field_id' => $chapterField->id,
            'field_name' => 'Chapitre',
            'field_type' => SubjectRequestField::TYPE_INTEGER,
            'value' => '2',
        ]);
        $this->assertDatabaseHas('support_request_field_answers', [
            'support_request_id' => $supportRequest->id,
            'subject_request_field_id' => $durationField->id,
            'field_name' => 'Duree',
            'field_type' => SubjectRequestField::TYPE_DECIMAL,
            'value' => '1.5',
        ]);
        $this->assertDatabaseHas('support_request_field_answers', [
            'support_request_id' => $supportRequest->id,
            'subject_request_field_id' => $topicField->id,
            'field_name' => 'Sujet',
            'field_type' => SubjectRequestField::TYPE_TEXT,
            'value' => 'Equations',
        ]);
    }

    public function test_student_cannot_create_request_when_current_classroom_is_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        try {
            $student = User::factory()->create();
            $classroom = Classroom::factory()->create();
            $subject = Subject::factory()->create([
                'classroom_id' => $classroom->id,
            ]);
            ClassroomOpeningHour::factory()->create([
                'classroom_id' => $classroom->id,
                'days' => [1],
                'opens_at' => '08:00',
                'closes_at' => '09:00',
            ]);

            $this
                ->actingAs($student)
                ->withSession(['current_classroom_id' => $classroom->id])
                ->get(route('student.requests.create'))
                ->assertRedirect(route('student.dashboard'))
                ->assertSessionHas('toast', [
                    'type' => 'warning',
                    'message' => 'Unable to create a request right now because the room is closed.',
                ]);

            $this
                ->actingAs($student)
                ->withSession(['current_classroom_id' => $classroom->id])
                ->post(route('student.requests.store'), [
                    'subject_id' => $subject->id,
                    'moodle_tile_number' => 42,
                    'table_number' => '8',
                ])
                ->assertRedirect(route('student.dashboard'))
                ->assertSessionHas('toast', [
                    'type' => 'warning',
                    'message' => 'Unable to create a request right now because the room is closed.',
                ]);

            $this->assertDatabaseCount('support_requests', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_student_subject_choices_use_selected_classroom_associations(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $availableSubject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Available subject',
        ]);
        $unassociatedSubject = Subject::factory()->create([
            'classroom_id' => null,
            'name' => 'Unassociated subject',
        ]);
        $otherClassroomSubject = Subject::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'name' => 'Other room subject',
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.requests.create'));

        $response
            ->assertOk()
            ->assertSee($availableSubject->name)
            ->assertDontSee($unassociatedSubject->name)
            ->assertDontSee($otherClassroomSubject->name);
    }

    public function test_student_request_form_hides_request_type_when_none_are_configured(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.requests.create'))
            ->assertOk()
            ->assertDontSee('name="request_type_id"', false);
    }

    public function test_student_request_form_shows_configured_request_types(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        RequestType::factory()->create([
            'name' => 'Correction',
            'sort_order' => 2,
        ]);
        RequestType::factory()->create([
            'name' => 'Explication',
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.requests.create'))
            ->assertOk()
            ->assertSee('name="request_type_id"', false)
            ->assertSee('Explication')
            ->assertSee('Correction');
    }

    public function test_student_request_type_is_required_when_setting_is_enabled(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $moodleTileField = SubjectRequestField::factory()->required()->integer()->create([
            'subject_id' => $subject->id,
            'name' => 'Tuile Moodle',
            'key' => SubjectRequestField::keyForName('Tuile Moodle'),
        ]);

        RequestType::factory()->create([
            'name' => 'Correction',
            'sort_order' => 1,
        ]);
        app(ApplicationSettings::class)->updateRequestTypeRequired(true);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.requests.create'))
            ->assertOk()
            ->assertSee('name="request_type_id"', false)
            ->assertSee('required', false);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 42,
                'table_number' => '8',
            ])
            ->assertSessionHasErrors('request_type_id');

        $this->assertDatabaseCount('support_requests', 0);
    }

    public function test_student_request_type_is_optional_when_setting_is_enabled_but_no_types_exist(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        app(ApplicationSettings::class)->updateRequestTypeRequired(true);

        $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 42,
                'table_number' => '8',
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('support_requests', [
            'student_id' => $student->id,
            'request_type' => null,
        ]);
    }

    public function test_student_can_have_only_one_active_request(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 42,
                'table_number' => '8',
            ]);

        $response
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('toast', [
                'type' => 'info',
                'message' => 'You already have an active request.',
            ]);

        $this->assertSame(1, SupportRequest::query()->where('student_id', $student->id)->whereIn('status', SupportRequest::activeStatuses())->count());
    }

    public function test_student_must_choose_classroom_before_creating_request(): void
    {
        $student = User::factory()->create();
        $subject = Subject::factory()->create();

        $response = $this->actingAs($student)->post(route('student.requests.store'), [
            'subject_id' => $subject->id,
            'moodle_tile_number' => 12,
            'table_number' => '3',
        ]);

        $response
            ->assertRedirect(route('student.classroom.edit'))
            ->assertSessionHas('toast', [
                'type' => 'info',
                'message' => 'Please choose a room before creating a request.',
            ]);
    }

    public function test_student_can_update_waiting_request(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $moodleTileField = SubjectRequestField::factory()->required()->integer()->create([
            'subject_id' => $subject->id,
            'name' => 'Tuile Moodle',
            'key' => SubjectRequestField::keyForName('Tuile Moodle'),
        ]);
        $requestType = RequestType::factory()->create([
            'name' => 'Correction',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($student)->patch(route('student.requests.update', $supportRequest), [
            'subject_id' => $subject->id,
            'request_fields' => [
                $moodleTileField->id => 7,
            ],
            'table_number' => '12',
            'request_type_id' => $requestType->id,
            'comment' => 'Correction svp.',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $supportRequest->refresh();

        $this->assertSame($subject->id, $supportRequest->subject_id);
        $this->assertSame(7, $supportRequest->moodle_tile_number);
        $this->assertSame('12', $supportRequest->table_number);
        $this->assertSame('Correction', $supportRequest->request_type);
        $this->assertDatabaseHas('support_request_field_answers', [
            'support_request_id' => $supportRequest->id,
            'subject_request_field_id' => $moodleTileField->id,
            'field_name' => 'Tuile Moodle',
            'value' => '7',
        ]);
    }

    public function test_student_request_type_is_required_when_editing_if_setting_is_enabled(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        RequestType::factory()->create([
            'name' => 'Correction',
            'sort_order' => 1,
        ]);
        app(ApplicationSettings::class)->updateRequestTypeRequired(true);

        $this
            ->actingAs($student)
            ->get(route('student.requests.edit', $supportRequest))
            ->assertOk()
            ->assertSee('name="request_type_id"', false)
            ->assertSee('required', false);

        $this
            ->actingAs($student)
            ->patch(route('student.requests.update', $supportRequest), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 7,
                'table_number' => '12',
            ])
            ->assertSessionHasErrors('request_type_id');
    }

    public function test_student_edit_preserves_copied_request_type_when_original_type_was_deleted(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'type' => '',
            'request_type' => 'Ancien type',
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);

        RequestType::factory()->create([
            'name' => 'Nouveau type',
            'sort_order' => 1,
        ]);

        $this->actingAs($student)->patch(route('student.requests.update', $supportRequest), [
            'subject_id' => $subject->id,
            'moodle_tile_number' => 7,
            'table_number' => '12',
            'comment' => 'Je modifie seulement le commentaire.',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertSame('Ancien type', $supportRequest->refresh()->request_type);
    }

    public function test_student_can_cancel_waiting_request(): void
    {
        $student = User::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this->actingAs($student)->patch(route('student.requests.cancel', $supportRequest));

        $response->assertRedirect();

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->refresh()->status);
        $this->assertSame(SupportRequest::CANCELLED_BY_STUDENT, $supportRequest->cancelled_by);
        $this->assertSame(SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED, $supportRequest->cancel_reason);
    }

    public function test_student_cancel_refreshes_without_error_when_request_was_already_assigned(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        $response = $this->actingAs($student)->patch(route('student.requests.cancel', $supportRequest));

        $response
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'info',
                'message' => 'The request has been updated.',
            ]);

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
    }

    public function test_student_cannot_cancel_assigned_request_from_active_request_card(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->call('confirmAssignedCancellation', $supportRequest->id)
            ->assertDispatched('toast');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertNull($supportRequest->cancelled_by);
        $this->assertNull($supportRequest->cancel_reason);
    }

    public function test_student_assigned_cancellation_button_is_icon_only_and_discreet(): void
    {
        $student = User::factory()->create();
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => User::factory()->teacher()->create()->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('aria-label="Cancel request"', false)
            ->assertSee('text-gray-300', false)
            ->assertDontSee('Cancel my request');
    }

    public function test_student_active_requests_component_uses_keep_alive_polling_when_active_request_exists(): void
    {
        $student = User::factory()->create();

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('wire:init="refreshPageTitle"', false)
            ->assertDontSee('wire:poll.3s.keep-alive="refreshPageTitle"', false)
            ->assertDontSee('wire:poll.3s.visible', false);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('wire:init="refreshPageTitle"', false)
            ->assertSee('wire:poll.3s.keep-alive="refreshPageTitle"', false)
            ->assertDontSee('wire:poll.3s.visible', false)
            ->assertDontSee('Active requests');
    }

    public function test_student_active_requests_updates_page_title_from_request_status(): void
    {
        $student = User::factory()->create();

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->call('refreshPageTitle')
            ->assertDispatched('page-title-updated', function (string $event, array $params): bool {
                return $params['title'] === 'Waiting - LineUp';
            })
            ->assertDispatched('teacher-page-title-updated', function (string $event, array $params): bool {
                return $params['title'] === 'Waiting - LineUp';
            });
    }

    public function test_student_active_requests_page_title_prioritizes_taken_requests(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create([
            'first_name' => 'Marie',
            'last_name' => 'Gagnon',
        ]);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
            'created_at' => now(),
        ]);
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
            'created_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->call('refreshPageTitle')
            ->assertDispatched('page-title-updated', function (string $event, array $params): bool {
                return $params['title'] === 'Taken by Marie Gagnon - LineUp';
            });
    }

    public function test_student_active_requests_page_title_resets_when_no_request_is_active(): void
    {
        $student = User::factory()->create();

        SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->call('refreshPageTitle')
            ->assertDispatched('page-title-updated', function (string $event, array $params): bool {
                return $params['title'] === 'LineUp';
            });
    }

    public function test_student_livewire_cancel_uses_conditional_update(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $supportRequest->update([
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->call('cancel', $supportRequest->id)
            ->assertDispatched('toast');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
    }

    public function test_student_active_request_badges_distinguish_assignment_and_progress_status(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create(['first_name' => 'Pierre']);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Taken by Pierre')
            ->assertDontSee('>Assigned<', false);

        SupportRequest::query()->where('student_id', $student->id)->update([
            'status' => SupportRequest::STATUS_PAUSED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Taken by Pierre')
            ->assertSee('Paused');

        SupportRequest::query()->where('student_id', $student->id)->update([
            'status' => SupportRequest::STATUS_READY,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Taken by Pierre')
            ->assertSee('Ready to review');
    }

    public function test_student_can_mark_paused_request_as_ready(): void
    {
        $student = User::factory()->create();
        $supportRequest = SupportRequest::factory()->paused()->create([
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($student)->patch(route('student.requests.ready', $supportRequest));

        $response->assertRedirect();

        $this->assertSame(SupportRequest::STATUS_READY, $supportRequest->refresh()->status);
    }

    public function test_student_can_not_update_assigned_request(): void
    {
        $student = User::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => User::factory()->teacher()->create()->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $subject = Subject::factory()->create();

        $response = $this->actingAs($student)->patch(route('student.requests.update', $supportRequest), [
            'subject_id' => $subject->id,
            'moodle_tile_number' => 9,
            'table_number' => '1',
        ]);

        $response->assertForbidden();
    }

    public function test_student_can_not_create_request_with_subject_from_another_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $otherClassroom->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 42,
                'table_number' => '8',
            ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_dashboard_auto_selects_active_request_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this->actingAs($student)->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSessionHas('current_classroom_id', $classroom->id);
    }

    public function test_student_history_shows_completed_and_cancelled_requests(): void
    {
        $student = User::factory()->create();
        $completed = SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
        ]);
        $cancelled = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_CANCELLED,
        ]);
        $active = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this->actingAs($student)->get(route('student.history'));

        $response
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSee('History')
            ->assertSee($completed->subject->name)
            ->assertSee($cancelled->subject->name)
            ->assertDontSee($active->subject->name);
    }

    public function test_student_history_survives_deleted_reference_data(): void
    {
        $student = User::factory()->create();
        $supportRequest = SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
        ]);

        $supportRequest->subject->delete();
        $supportRequest->classroom->delete();

        $response = $this->actingAs($student)->get(route('student.history'));

        $response
            ->assertOk()
            ->assertSee('N/A');
    }

    public function test_student_history_uses_final_status_badges(): void
    {
        $student = User::factory()->create();
        SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
        ]);
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($student)->get(route('student.history'));

        $response
            ->assertOk()
            ->assertSee('Completed')
            ->assertSee('Cancelled');
    }
}
