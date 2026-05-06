<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\MyRequests;
use App\Livewire\Teacher\OtherTeacherRequests;
use App\Livewire\Teacher\RequestChangeWatcher;
use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_dashboard_redirects_to_classroom_choice_when_no_classroom_is_selected(): void
    {
        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertRedirect(route('teacher.classroom.edit'));
    }

    public function test_teacher_can_choose_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        $response = $this->actingAs($teacher)->put(route('teacher.classroom.update'), [
            'classroom_id' => $classroom->id,
        ]);

        $response
            ->assertRedirect(route('teacher.dashboard'))
            ->assertSessionHas('current_classroom_id', $classroom->id);
    }

    public function test_teacher_can_not_change_classroom_with_active_assigned_request_in_another_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $currentClassroom = Classroom::factory()->create();
        $newClassroom = Classroom::factory()->create();
        SupportRequest::factory()->paused()->create([
            'classroom_id' => $currentClassroom->id,
            'assigned_teacher_id' => $teacher->id,
        ]);

        $response = $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $currentClassroom->id])
            ->put(route('teacher.classroom.update'), [
                'classroom_id' => $newClassroom->id,
            ]);

        $response->assertSessionHasErrors('classroom_id');
    }

    public function test_teacher_dashboard_auto_selects_assigned_request_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response
            ->assertOk()
            ->assertSessionHas('current_classroom_id', $classroom->id);
    }

    public function test_teacher_can_assign_waiting_request_from_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assign', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $supportRequest->refresh();

        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertNotNull($supportRequest->assigned_at);
        $this->assertSame(1, app(SupportRequestChangeMarker::class)->current($classroom->id));
    }

    public function test_teacher_assignment_uses_conditional_update_against_double_assignment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assign', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $this->assertSame($otherTeacher->id, $supportRequest->refresh()->assigned_teacher_id);
    }

    public function test_teacher_can_cancel_only_waiting_request_from_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $localRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);
        $otherRequest = SupportRequest::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('confirmCancel', $localRequest->id)
            ->assertSee('Annuler la demande')
            ->call('cancel')
            ->assertDispatched('toast')
            ->call('confirmCancel', $otherRequest->id)
            ->call('cancel')
            ->assertDispatched('toast');

        $this->assertSame(SupportRequest::STATUS_CANCELLED, $localRequest->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_WAITING, $otherRequest->refresh()->status);
    }

    public function test_teacher_can_complete_pause_and_unassign_only_own_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $assigned = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $ready = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_READY,
            'assigned_at' => now(),
        ]);
        $otherRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('pause', $assigned->id)
            ->assertDispatched('toast')
            ->call('unassign', $ready->id)
            ->assertDispatched('toast')
            ->call('complete', $otherRequest->id);

        $this->assertSame(SupportRequest::STATUS_PAUSED, $assigned->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_WAITING, $ready->refresh()->status);
        $this->assertNull($ready->assigned_teacher_id);
        $this->assertNull($ready->assigned_at);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $otherRequest->refresh()->status);
    }

    public function test_teacher_can_manage_other_teacher_active_requests_from_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $toRequeue = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $toComplete = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now(),
        ]);
        $toCancel = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_READY,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(OtherTeacherRequests::class)
            ->call('openManagementModal', $toRequeue->id)
            ->assertSee('Gerer la demande')
            ->call('requeue')
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->call('openManagementModal', $toComplete->id)
            ->call('complete')
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->call('openManagementModal', $toCancel->id)
            ->call('cancel')
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $this->assertSame(SupportRequest::STATUS_WAITING, $toRequeue->refresh()->status);
        $this->assertNull($toRequeue->assigned_teacher_id);
        $this->assertNull($toRequeue->assigned_at);
        $this->assertSame(SupportRequest::STATUS_COMPLETED, $toComplete->refresh()->status);
        $this->assertNotNull($toComplete->completed_at);
        $this->assertSame(SupportRequest::STATUS_CANCELLED, $toCancel->refresh()->status);
        $this->assertSame(SupportRequest::CANCELLED_BY_TEACHER, $toCancel->cancelled_by);
        $this->assertSame(SupportRequest::CANCEL_REASON_TEACHER_CANCELLED, $toCancel->cancel_reason);
        $this->assertSame(3, app(SupportRequestChangeMarker::class)->current($classroom->id));
    }

    public function test_teacher_can_not_manage_requests_outside_other_teacher_visible_section(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $ownRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $otherClassroomRequest = SupportRequest::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(OtherTeacherRequests::class)
            ->set('managingRequestId', $ownRequest->id)
            ->call('complete')
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->set('managingRequestId', $otherClassroomRequest->id)
            ->call('cancel')
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $ownRequest->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $otherClassroomRequest->refresh()->status);
    }

    public function test_teacher_my_requests_are_ordered_by_creation_date_and_hide_pause_on_paused_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $pausedOnlyClassroom = Classroom::factory()->create();
        $newerAssigned = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'created_at' => now()->subMinutes(5),
        ]);
        $olderPaused = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(20),
        ]);
        SupportRequest::factory()->create([
            'classroom_id' => $pausedOnlyClassroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSeeInOrder([
                $olderPaused->student->name,
                $newerAssigned->student->name,
            ])
            ->assertSee('En pause')
            ->assertSee('Mettre en pause');

        session(['current_classroom_id' => $pausedOnlyClassroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertDontSee('Mettre en pause');
    }

    public function test_teacher_request_change_watcher_dispatches_refresh_only_when_classroom_marker_changes(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        session(['current_classroom_id' => $classroom->id]);

        $component = Livewire::actingAs($teacher)
            ->test(RequestChangeWatcher::class)
            ->call('check')
            ->assertNotDispatched('teacher-requests-updated');

        app(SupportRequestChangeMarker::class)->touch($classroom->id);

        $component
            ->call('check')
            ->assertDispatched('teacher-requests-updated');
    }

    public function test_teacher_lists_no_longer_poll_independently(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        $response = $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('teacher.dashboard'));

        $response
            ->assertOk()
            ->assertSee('wire:poll.2s="check"', false)
            ->assertDontSee('wire:poll.8s.visible', false)
            ->assertDontSee('wire:poll.10s.visible', false);
    }
}
