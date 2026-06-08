<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\ActiveRequests;
use App\Models\Classroom;
use App\Models\RequestType;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ApplicationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee(route('student.classroom.edit'), false);
    }

    public function test_student_must_confirm_classroom_change_when_active_request_exists(): void
    {
        $student = User::factory()->create();
        $currentClassroom = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $newClassroom->id,
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $currentClassroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $currentClassroom->id])
            ->put(route('student.classroom.update'), [
                'classroom_id' => $newClassroom->id,
            ]);

        $response->assertSessionHasErrors('confirm_cancel_active_requests');
        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->refresh()->status);
    }

    public function test_confirmed_classroom_change_cancels_active_requests(): void
    {
        $student = User::factory()->create();
        $currentClassroom = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $newClassroom->id,
        ]);
        $supportRequest = SupportRequest::factory()->paused()->create([
            'student_id' => $student->id,
            'classroom_id' => $currentClassroom->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $currentClassroom->id])
            ->put(route('student.classroom.update'), [
                'classroom_id' => $newClassroom->id,
                'confirm_cancel_active_requests' => '1',
            ]);

        $response
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('current_classroom_id', $newClassroom->id);

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->status);
        $this->assertNull($supportRequest->assigned_teacher_id);
        $this->assertNull($supportRequest->assigned_at);
    }

    public function test_student_can_create_support_request_with_current_classroom(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
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
                'moodle_tile_number' => 42,
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
        $requestType = RequestType::factory()->create([
            'name' => 'Correction',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($student)->patch(route('student.requests.update', $supportRequest), [
            'subject_id' => $subject->id,
            'moodle_tile_number' => 7,
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

    public function test_student_can_cancel_assigned_request_after_confirmation(): void
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
            ->assertSee('This request has already been taken by a teacher. Do you really want to cancel it?')
            ->assertSee('Cancel my request')
            ->call('cancelAssignedRequest')
            ->assertDispatched('toast');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $supportRequest->status);
        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::CANCELLED_BY_STUDENT, $supportRequest->cancelled_by);
        $this->assertSame(SupportRequest::CANCEL_REASON_NO_LONGER_NEEDED, $supportRequest->cancel_reason);
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

    public function test_student_active_requests_component_polls_only_when_active_request_exists(): void
    {
        $student = User::factory()->create();

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertDontSee('wire:poll.3s.visible', false);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('wire:poll.3s.visible', false)
            ->assertDontSee('Active requests');
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
