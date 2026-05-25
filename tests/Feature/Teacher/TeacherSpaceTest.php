<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\DashboardView;
use App\Livewire\Teacher\MyRequests;
use App\Livewire\Teacher\OtherTeacherRequests;
use App\Livewire\Teacher\RequestChangeWatcher;
use App\Livewire\Teacher\RequestHistory;
use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\Subject;
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

    public function test_teacher_classroom_choice_shows_teacher_breadcrumb(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.classroom.edit'))
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSee('Teacher');
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

    public function test_teacher_dashboard_shows_current_classroom_breadcrumb(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 203']);

        $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Teacher')
            ->assertSee('Local 203')
            ->assertSee(route('teacher.classroom.edit'), false)
            ->assertDontSee('Change room');
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

    public function test_my_requests_refresh_keeps_priority_and_regular_requests_after_assignment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $priorityRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'Assistance prioritaire',
            'assigned_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(30),
        ]);
        $existingRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(20),
        ]);
        $waitingRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'created_at' => now()->subMinutes(5),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        $myRequests = Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee($priorityRequest->comment)
            ->assertSee('Since 20 min')
            ->assertSee($existingRequest->student->fullName())
            ->assertSee('Since 10 min')
            ->assertDontSee('minutes')
            ->assertDontSee($waitingRequest->student->fullName());

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assign', $waitingRequest->id)
            ->assertDispatched('teacher-requests-updated');

        $myRequests
            ->call('refreshRequests')
            ->assertSet('refreshKey', 1)
            ->assertSee($priorityRequest->comment)
            ->assertSee($existingRequest->student->fullName())
            ->assertSee($waitingRequest->student->fullName());
    }

    public function test_completing_priority_request_keeps_regular_my_requests_visible(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $priorityRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'Priorite a terminer',
            'assigned_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(15),
        ]);
        $regularRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(10),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee($priorityRequest->comment)
            ->assertSee($regularRequest->student->fullName())
            ->call('complete', $priorityRequest->id)
            ->assertSet('refreshKey', 1)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->assertDontSee($priorityRequest->comment)
            ->assertSee($regularRequest->student->fullName());

        $this->assertSame(SupportRequest::STATUS_COMPLETED, $priorityRequest->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $regularRequest->refresh()->status);
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

    public function test_teacher_can_assign_and_complete_waiting_request_from_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
            'assigned_at' => null,
            'completed_at' => null,
            'is_priority' => false,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSee('Take and complete')
            ->assertSee('Cancel this request')
            ->call('assignAndComplete', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $supportRequest->refresh();

        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_COMPLETED, $supportRequest->status);
        $this->assertNotNull($supportRequest->assigned_at);
        $this->assertNotNull($supportRequest->completed_at);
        $this->assertSame(1, app(SupportRequestChangeMarker::class)->current($classroom->id));
    }

    public function test_teacher_can_not_assign_and_complete_priority_request_from_waiting_queue_menu_action(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
            'is_priority' => true,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assignAndComplete', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $supportRequest->refresh();

        $this->assertNull($supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->status);
        $this->assertNull($supportRequest->completed_at);
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
            ->assertSee('Cancel request')
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
            ->assertSee('Manage request')
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

    public function test_teacher_my_requests_are_ordered_by_priority_then_assignment_date_and_hide_pause_on_paused_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $pausedOnlyClassroom = Classroom::factory()->create();
        $olderAssigned = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(5),
        ]);
        $priorityRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'is_priority' => true,
            'priority_requested_by_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'comment' => 'Priorite recente',
            'assigned_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);
        $olderPaused = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now(),
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
                $priorityRequest->comment,
                $olderPaused->student->fullName(),
                $olderAssigned->student->fullName(),
            ])
            ->assertDontSee('Assigned')
            ->assertSee('Paused')
            ->assertSee('bg-amber-100', false)
            ->assertSee('opacity-60', false)
            ->assertSee('bg-emerald-600', false)
            ->assertSee('Pause');

        session(['current_classroom_id' => $pausedOnlyClassroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertDontSee('>Pause<', false);
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

    public function test_teacher_dashboard_page_title_shows_waiting_count_and_first_active_student(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $firstActiveStudent = User::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Tremblay',
        ]);
        $olderActiveStudent = User::factory()->create([
            'first_name' => 'Marie',
            'last_name' => 'Gagnon',
        ]);

        SupportRequest::factory()->count(3)->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        SupportRequest::factory()->create([
            'student_id' => $olderActiveStudent->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(5),
        ]);

        SupportRequest::factory()->create([
            'student_id' => $firstActiveStudent->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(DashboardView::class)
            ->assertSet('pageTitle', '(3) - Jean Tremblay - LineUp');
    }

    public function test_teacher_dashboard_page_title_shows_waiting_count_without_active_student(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        SupportRequest::factory()->count(5)->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(DashboardView::class)
            ->assertSet('pageTitle', '(5) - LineUp');
    }

    public function test_teacher_dashboard_page_title_refreshes_when_teacher_requests_change(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $student = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Roy',
        ]);

        session(['current_classroom_id' => $classroom->id]);

        $component = Livewire::actingAs($teacher)
            ->test(DashboardView::class)
            ->assertSet('pageTitle', '(0) - LineUp');

        SupportRequest::factory()->count(2)->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $component
            ->dispatch('teacher-requests-updated')
            ->assertSet('pageTitle', '(2) - Alice Roy - LineUp')
            ->assertDispatched('teacher-page-title-updated', function (string $event, array $params): bool {
                return $params['title'] === '(2) - Alice Roy - LineUp';
            });
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
            ->assertSee('wire:init="updatePageTitle"', false)
            ->assertDontSee('wire:poll.8s.visible', false)
            ->assertDontSee('wire:poll.10s.visible', false)
            ->assertDontSee('wire:poll.2s.keep-alive="updatePageTitle"', false);
    }

    public function test_teacher_dashboard_links_to_distinct_history_page(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(DashboardView::class)
            ->assertSee('View history')
            ->assertSee(route('teacher.history'), false)
            ->assertSee('teacher.my-requests', false)
            ->assertSee('teacher.waiting-queue', false)
            ->assertDontSee('teacher.request-history', false);
    }

    public function test_teacher_history_is_a_distinct_page_for_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 203']);

        $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('teacher.history'))
            ->assertOk()
            ->assertSee('History')
            ->assertSee('Local 203')
            ->assertSee('Back to requests')
            ->assertSee(route('teacher.classroom.edit'), false)
            ->assertSee(route('teacher.dashboard'), false)
            ->assertSee('teacher.request-history', false)
            ->assertDontSee('teacher.my-requests', false)
            ->assertDontSee('teacher.waiting-queue', false);
    }

    public function test_teacher_history_redirects_to_classroom_choice_when_no_classroom_is_selected(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.history'))
            ->assertRedirect(route('teacher.classroom.edit'));
    }

    public function test_teacher_history_defaults_to_today_and_current_classroom(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $todayRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ]);
        $oldRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'assigned_teacher_id' => $teacher->id,
            'created_at' => now()->subDay(),
        ]);
        $otherClassroomRequest = SupportRequest::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'created_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->assertSee('History')
            ->assertSee($todayRequest->student->fullName())
            ->assertDontSee($oldRequest->student->fullName())
            ->assertDontSee($otherClassroomRequest->student->fullName());
    }

    public function test_teacher_history_filters_by_period_teacher_and_search(): void
    {
        $teacher = User::factory()->teacher()->create(['first_name' => 'Pierre']);
        $otherTeacher = User::factory()->teacher()->create(['first_name' => 'Jean']);
        $classroom = Classroom::factory()->create();
        $networkSubject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Reseau',
        ]);
        $mathSubject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Math',
        ]);
        $oldNetworkRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $networkSubject->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'comment' => 'Probleme de reseau',
            'created_at' => now()->subDays(3),
        ]);
        $todayMathRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $mathSubject->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_CANCELLED,
            'comment' => 'Calcul',
            'created_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->assertSee($todayMathRequest->student->fullName())
            ->assertDontSee($oldNetworkRequest->student->fullName())
            ->set('period', 'custom')
            ->set('startDate', now()->subDays(4)->toDateString())
            ->set('endDate', now()->subDays(2)->toDateString())
            ->assertSee($oldNetworkRequest->student->fullName())
            ->assertDontSee($todayMathRequest->student->fullName())
            ->set('period', 'all')
            ->assertSee($oldNetworkRequest->student->fullName())
            ->set('teacherFilter', (string) $otherTeacher->id)
            ->assertSee($oldNetworkRequest->student->fullName())
            ->assertDontSee($todayMathRequest->student->fullName())
            ->set('search', 'reseau')
            ->assertSee($oldNetworkRequest->student->fullName())
            ->set('search', 'math')
            ->assertDontSee($oldNetworkRequest->student->fullName());
    }
}
