<?php

namespace Tests\Feature\Student;

use App\Livewire\Student\ActiveRequests;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
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

    public function test_student_must_confirm_classroom_change_when_active_request_exists(): void
    {
        $student = User::factory()->create();
        $currentClassroom = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
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

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->post(route('student.requests.store'), [
                'subject_id' => $subject->id,
                'moodle_tile_number' => 42,
                'table_number' => '8',
                'type' => SupportRequest::TYPE_VALIDATION,
                'comment' => 'Je veux valider mon exercice.',
            ]);

        $response->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('support_requests', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'moodle_tile_number' => 42,
            'table_number' => '8',
            'type' => SupportRequest::TYPE_VALIDATION,
            'status' => SupportRequest::STATUS_WAITING,
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
                'type' => SupportRequest::TYPE_VALIDATION,
            ]);

        $response
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHasErrors('support_request');

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
            'type' => SupportRequest::TYPE_EXPLANATION,
        ]);

        $response
            ->assertRedirect(route('student.classroom.edit'))
            ->assertSessionHasErrors('classroom');
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

        $response = $this->actingAs($student)->patch(route('student.requests.update', $supportRequest), [
            'subject_id' => $subject->id,
            'moodle_tile_number' => 7,
            'table_number' => '12',
            'type' => SupportRequest::TYPE_CORRECTION,
            'comment' => 'Correction svp.',
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $supportRequest->refresh();

        $this->assertSame($subject->id, $supportRequest->subject_id);
        $this->assertSame(7, $supportRequest->moodle_tile_number);
        $this->assertSame('12', $supportRequest->table_number);
        $this->assertSame(SupportRequest::TYPE_CORRECTION, $supportRequest->type);
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
            ->assertSessionHas('status', 'La demande a ete mise a jour.');

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
            ->assertSee("Cette demande est déjà prise en charge par un enseignant. Voulez-vous vraiment l'annuler ?")
            ->assertSee('Annuler ma demande')
            ->call('cancelAssignedRequest')
            ->assertSee('Demande annulee.');

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
            ->assertSee('aria-label="Annuler la demande"', false)
            ->assertSee('text-gray-300', false)
            ->assertDontSee('Annuler ma demande');
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
            ->assertDontSee('Demandes actives');
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
            ->assertSee('La demande a ete mise a jour.');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
    }

    public function test_student_active_request_badges_distinguish_assignment_and_progress_status(): void
    {
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->create(['name' => 'Pierre']);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Pris en charge par Pierre')
            ->assertDontSee('Attribuee');

        SupportRequest::query()->where('student_id', $student->id)->update([
            'status' => SupportRequest::STATUS_PAUSED,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Pris en charge par Pierre')
            ->assertSee('En pause');

        SupportRequest::query()->where('student_id', $student->id)->update([
            'status' => SupportRequest::STATUS_READY,
        ]);

        Livewire::actingAs($student)
            ->test(ActiveRequests::class)
            ->assertSee('Pris en charge par Pierre')
            ->assertSee('Prêt à revoir');
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
            'type' => SupportRequest::TYPE_EXPLANATION,
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
                'type' => SupportRequest::TYPE_VALIDATION,
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
            ->assertSee('Terminée')
            ->assertSee('Annulée');
    }
}
