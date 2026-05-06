<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\MyRequests;
use App\Livewire\Teacher\PriorityRequests;
use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriorityRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_access_priority_requests_without_selected_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.priority-requests.index'))
            ->assertOk()
            ->assertSee('Demandes prioritaires');
    }

    public function test_teacher_can_create_priority_request_without_student_fields(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        Livewire::actingAs($teacher)
            ->test(PriorityRequests::class)
            ->assertSet('message', PriorityRequests::DEFAULT_MESSAGE)
            ->assertSet('formResetKey', 0)
            ->set('classroomId', $classroom->id)
            ->set('message', 'Besoin d un deuxieme avis.')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('classroomId', null)
            ->assertSet('message', PriorityRequests::DEFAULT_MESSAGE)
            ->assertSet('formResetKey', 1)
            ->assertDispatched('toast');

        $supportRequest = SupportRequest::query()->firstOrFail();

        $this->assertTrue($supportRequest->is_priority);
        $this->assertSame($teacher->id, $supportRequest->priority_requested_by_teacher_id);
        $this->assertSame($classroom->id, $supportRequest->classroom_id);
        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->status);
        $this->assertSame('Besoin d un deuxieme avis.', $supportRequest->comment);
        $this->assertNull($supportRequest->student_id);
        $this->assertNull($supportRequest->subject_id);
        $this->assertNull($supportRequest->moodle_tile_number);
        $this->assertNull($supportRequest->table_number);
        $this->assertNull($supportRequest->type);
        $this->assertSame(1, app(SupportRequestChangeMarker::class)->current($classroom->id));
    }

    public function test_sent_priority_requests_refresh_when_target_classroom_marker_changes(): void
    {
        $requester = User::factory()->teacher()->create();
        $targetTeacher = User::factory()->teacher()->create(['name' => 'Jean']);
        $classroom = Classroom::factory()->create();
        $priorityRequest = SupportRequest::factory()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => null,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $requester->id,
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'comment' => 'Assistance en salle d’évaluation',
        ]);

        $component = Livewire::actingAs($requester)
            ->test(PriorityRequests::class)
            ->assertSee('Assistance en salle d’évaluation')
            ->assertDontSee('Pris par Jean');

        $priorityRequest->update([
            'assigned_teacher_id' => $targetTeacher->id,
            'assigned_at' => now(),
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);
        app(SupportRequestChangeMarker::class)->touch($classroom->id);

        $component
            ->call('checkForPriorityRequestChanges')
            ->assertSee('Pris par')
            ->assertSee('Jean');

        $priorityRequest->update([
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        app(SupportRequestChangeMarker::class)->touch($classroom->id);

        $component
            ->call('checkForPriorityRequestChanges')
            ->assertDontSee('Assistance en salle d’évaluation');
    }

    public function test_priority_requests_are_shown_before_student_requests_and_can_be_assigned_once(): void
    {
        $requester = User::factory()->teacher()->create(['name' => 'Pierre']);
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $studentRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'created_at' => now()->subHour(),
        ]);
        $priorityRequest = SupportRequest::factory()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => null,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $requester->id,
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'comment' => 'Peux-tu venir voir un probleme technique ?',
            'created_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSeeInOrder(['Prioritaire', $studentRequest->student->name])
            ->call('assign', $priorityRequest->id)
            ->assertDispatched('toast');

        $priorityRequest->refresh();

        $this->assertSame($teacher->id, $priorityRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $priorityRequest->status);

        Livewire::actingAs($otherTeacher)
            ->test(WaitingQueue::class)
            ->call('assign', $priorityRequest->id)
            ->assertDispatched('toast');

        $this->assertSame($teacher->id, $priorityRequest->refresh()->assigned_teacher_id);
    }

    public function test_assigned_priority_request_appears_in_my_requests_with_only_complete_action(): void
    {
        $requester = User::factory()->teacher()->create(['name' => 'Jacques']);
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $priorityRequest = SupportRequest::factory()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => $teacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $requester->id,
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'Besoin d aide avec un eleve.',
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee('Prioritaire')
            ->assertSee('Jacques')
            ->assertSee('Besoin d aide avec un eleve.')
            ->assertSee('Terminer')
            ->assertDontSee('Mettre en pause')
            ->assertDontSee('Remettre dans la file')
            ->call('complete', $priorityRequest->id)
            ->assertDispatched('toast');

        $this->assertSame(SupportRequest::STATUS_COMPLETED, $priorityRequest->refresh()->status);
        $this->assertNotNull($priorityRequest->completed_at);
    }

    public function test_priority_request_creator_can_cancel_or_complete_active_requests_even_when_assigned(): void
    {
        $requester = User::factory()->teacher()->create();
        $assignedTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $toCancel = SupportRequest::factory()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => $assignedTeacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $requester->id,
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'A annuler',
            'assigned_at' => now(),
        ]);
        $toComplete = SupportRequest::factory()->create([
            'student_id' => null,
            'classroom_id' => $classroom->id,
            'subject_id' => null,
            'assigned_teacher_id' => $assignedTeacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $requester->id,
            'moodle_tile_number' => null,
            'table_number' => null,
            'type' => null,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'A terminer',
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($requester)
            ->test(PriorityRequests::class)
            ->call('cancel', $toCancel->id)
            ->assertDispatched('toast')
            ->call('complete', $toComplete->id)
            ->assertDispatched('toast');

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $toCancel->refresh()->status);
        $this->assertSame(SupportRequest::CANCELLED_BY_TEACHER, $toCancel->cancelled_by);
        $this->assertSame(SupportRequest::STATUS_COMPLETED, $toComplete->refresh()->status);
    }
}
