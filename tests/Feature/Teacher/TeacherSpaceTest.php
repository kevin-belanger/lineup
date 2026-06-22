<?php

namespace Tests\Feature\Teacher;

use App\Livewire\Teacher\DashboardView;
use App\Livewire\Teacher\MyRequests;
use App\Livewire\Teacher\OtherTeacherRequests;
use App\Livewire\Teacher\PersonalNotes;
use App\Livewire\Teacher\RequestChangeWatcher;
use App\Livewire\Teacher\RequestHistory;
use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use App\Models\PersonalNote;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\TeacherActiveRequestOrder;
use App\Models\User;
use App\Services\ApplicationSettings;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_teacher_classroom_choice_uses_clickable_room_cards(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create([
            'name' => 'Local 203',
            'description' => 'Aile B',
        ]);

        $this->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('teacher.classroom.edit'))
            ->assertOk()
            ->assertSessionMissing('current_classroom_id')
            ->assertSee('Choose a room')
            ->assertSee('Local 203')
            ->assertSee('Aile B')
            ->assertSee('type="radio"', false)
            ->assertSee('x-on:change="$root.requestSubmit()"', false)
            ->assertDontSee('checked="checked"', false)
            ->assertDontSee('Continue');
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

    public function test_teacher_request_cards_show_copied_request_type_when_present(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
            'type' => '',
            'request_type' => 'Validation rapide',
        ]);

        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
            'type' => '',
            'request_type' => 'Correction détaillée',
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSee('Validation rapide');

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee('Correction détaillée');
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
            ->assertSee('Wait 10 min')
            ->assertSee('Intervention 20 min')
            ->assertSee($existingRequest->student->fullName())
            ->assertSee('Intervention 10 min')
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

    public function test_teacher_completion_stores_calculated_durations_using_opening_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:30:00', 'UTC'));

        try {
            $teacher = User::factory()->teacher()->create();
            $classroom = Classroom::factory()->create();
            ClassroomOpeningHour::factory()->create([
                'classroom_id' => $classroom->id,
                'days' => [1],
                'opens_at' => '10:00',
                'closes_at' => '11:00',
                'sort_order' => 0,
            ]);
            ClassroomOpeningHour::factory()->create([
                'classroom_id' => $classroom->id,
                'days' => [1],
                'opens_at' => '12:00',
                'closes_at' => '13:00',
                'sort_order' => 1,
            ]);
            $supportRequest = SupportRequest::factory()->create([
                'classroom_id' => $classroom->id,
                'assigned_teacher_id' => $teacher->id,
                'status' => SupportRequest::STATUS_ASSIGNED,
                'created_at' => Carbon::parse('2026-06-15 10:55:00', 'UTC'),
                'assigned_at' => Carbon::parse('2026-06-15 12:15:00', 'UTC'),
                'completed_at' => null,
            ]);

            session(['current_classroom_id' => $classroom->id]);

            Livewire::actingAs($teacher)
                ->test(MyRequests::class)
                ->call('complete', $supportRequest->id)
                ->assertDispatched('toast')
                ->assertDispatched('teacher-requests-updated');

            $supportRequest->refresh();

            $this->assertSame(SupportRequest::STATUS_COMPLETED, $supportRequest->status);
            $this->assertSame(20, $supportRequest->calculated_wait_time_minutes);
            $this->assertSame(15, $supportRequest->calculated_response_time_minutes);
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_teacher_can_undo_completion_from_toast_action(): void
    {
        $teacher = User::factory()->teacher()->create(['place_new_requests_on_top' => true]);
        $classroom = Classroom::factory()->create();
        $createdAt = now()->subHour();
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(30),
            'created_at' => $createdAt,
            'comment' => 'Ne pas recréer la demande',
            'table_number' => '18',
            'moodle_tile_number' => 4,
            'request_type' => 'Correction',
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('complete', $supportRequest->id)
            ->assertDispatched('toast', fn (string $event, array $params): bool => $event === 'toast'
                && ($params['message'] ?? null) === 'Request completed.'
                && ($params['timeout'] ?? null) === 7000
                && ($params['action']['label'] ?? null) === 'Cancel'
                && ($params['action']['event'] ?? null) === 'undo-completed-request'
                && ($params['action']['payload']['supportRequestId'] ?? null) === $supportRequest->id)
            ->assertDispatched('teacher-requests-updated')
            ->call('undoComplete', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->assertSee($supportRequest->student->fullName());

        $supportRequest->refresh();

        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertNotNull($supportRequest->assigned_at);
        $this->assertNull($supportRequest->completed_at);
        $this->assertSame('Ne pas recréer la demande', $supportRequest->comment);
        $this->assertSame('18', $supportRequest->table_number);
        $this->assertSame(4, $supportRequest->moodle_tile_number);
        $this->assertSame('Correction', $supportRequest->request_type);
        $this->assertSame($createdAt->toDateTimeString(), $supportRequest->created_at->toDateTimeString());
        $this->assertDatabaseHas('teacher_active_request_orders', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
        ]);
    }

    public function test_teacher_cannot_undo_completion_after_request_changed_state(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinute(),
        ]);

        $supportRequest->update([
            'assigned_teacher_id' => null,
            'assigned_at' => null,
            'status' => SupportRequest::STATUS_WAITING,
            'completed_at' => null,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('undoComplete', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated');

        $supportRequest->refresh();

        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->status);
        $this->assertNull($supportRequest->assigned_teacher_id);
        $this->assertNull($supportRequest->completed_at);
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

    public function test_other_teacher_requests_translate_with_label_in_french(): void
    {
        app()->setLocale('fr');

        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(OtherTeacherRequests::class)
            ->assertSee('Avec')
            ->assertDontSee('With');
    }

    public function test_other_teacher_requests_use_harmonized_course_links(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Matiere avec un tres long nom qui doit revenir naturellement a la ligne',
            'url' => 'https://moodle.example.test/course?table=[table]&section=[section]',
        ]);

        app(ApplicationSettings::class)->updateReuseCourseUrlTab(true);

        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'moodle_tile_number' => 12,
            'table_number' => '7',
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(OtherTeacherRequests::class)
            ->assertSee('Matiere avec un tres long nom qui doit revenir naturellement a la ligne - Tile 12')
            ->assertSee('https://moodle.example.test/course?table=7&amp;section=12', false)
            ->assertSee('target="lineup_course_url"', false)
            ->assertSee('aria-label="Open the subject link"', false)
            ->assertSee('whitespace-normal break-words', false)
            ->call('openManagementModal', $supportRequest->id)
            ->assertSee('Matiere avec un tres long nom qui doit revenir naturellement a la ligne - Tile 12')
            ->assertSee('target="lineup_course_url"', false);
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

    public function test_teacher_can_manually_reorder_active_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $first = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(30),
        ]);
        $second = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(20),
        ]);
        $third = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(10),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('reorderRequests', [$second->id, $first->id, $third->id])
            ->assertDispatched('teacher-requests-updated')
            ->assertSeeInOrder([
                $second->student->fullName(),
                $first->student->fullName(),
                $third->student->fullName(),
            ]);

        $this->assertSame(1, app(SupportRequestChangeMarker::class)->current($classroom->id));

        $this->assertSame(3, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $second->id)
            ->value('sort_order'));
        $this->assertSame(2, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $first->id)
            ->value('sort_order'));
        $this->assertSame(1, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $third->id)
            ->value('sort_order'));

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSeeInOrder([
                $second->student->fullName(),
                $first->student->fullName(),
                $third->student->fullName(),
            ])
            ->assertSee('wire:sort="moveRequestToPosition"', false)
            ->assertSee('wire:sort:item="'.$second->id.'"', false)
            ->assertSee('wire:sort:handle', false)
            ->assertSee('Reorder request');
    }

    public function test_teacher_can_move_active_request_to_position_with_livewire_sort(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $first = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(30),
        ]);
        $second = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(20),
        ]);
        $third = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(10),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('moveRequestToPosition', $first->id, 1)
            ->assertDispatched('teacher-requests-updated')
            ->assertSeeInOrder([
                $third->student->fullName(),
                $first->student->fullName(),
                $second->student->fullName(),
            ]);

        $this->assertSame(3, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $third->id)
            ->value('sort_order'));
        $this->assertSame(2, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $first->id)
            ->value('sort_order'));
        $this->assertSame(1, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $second->id)
            ->value('sort_order'));
    }

    public function test_manual_active_request_order_is_scoped_to_connected_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $ownRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
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
            ->call('reorderRequests', [$otherRequest->id, $ownRequest->id]);

        $this->assertDatabaseHas('teacher_active_request_orders', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $ownRequest->id,
        ]);
        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $otherRequest->id,
        ]);
    }

    public function test_newly_assigned_request_is_added_to_top_of_manual_order(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $existing = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(5),
        ]);
        $waiting = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
        ]);
        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $existing->id,
            'sort_order' => 1,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assign', $waiting->id);

        $this->assertSame(2, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $waiting->id)
            ->value('sort_order'));

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSeeInOrder([
                $waiting->student->fullName(),
                $existing->student->fullName(),
            ]);
    }

    public function test_teacher_can_choose_to_add_newly_assigned_requests_to_bottom(): void
    {
        $teacher = User::factory()->teacher()->create([
            'place_new_requests_on_top' => false,
        ]);
        $classroom = Classroom::factory()->create();
        $existing = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(5),
        ]);
        $waiting = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => null,
            'status' => SupportRequest::STATUS_WAITING,
        ]);
        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $existing->id,
            'sort_order' => 1,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->call('assign', $waiting->id);

        $this->assertSame(0, TeacherActiveRequestOrder::query()
            ->where('teacher_id', $teacher->id)
            ->where('support_request_id', $waiting->id)
            ->value('sort_order'));

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSeeInOrder([
                $existing->student->fullName(),
                $waiting->student->fullName(),
            ]);
    }

    public function test_teacher_can_update_active_request_placement_preference_from_my_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSet('placeNewRequestsOnTop', true)
            ->assertSee('Active request options')
            ->assertSee('Place new requests at the top')
            ->set('placeNewRequestsOnTop', false)
            ->assertSet('placeNewRequestsOnTop', false);

        $this->assertFalse($teacher->refresh()->place_new_requests_on_top);
    }

    public function test_active_request_order_is_removed_when_request_leaves_my_requests(): void
    {
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $toComplete = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);
        $toUnassign = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);
        $managedByOtherTeacher = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $otherTeacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
        ]);

        foreach ([$toComplete, $toUnassign, $managedByOtherTeacher] as $index => $supportRequest) {
            TeacherActiveRequestOrder::query()->create([
                'teacher_id' => $supportRequest->assigned_teacher_id,
                'support_request_id' => $supportRequest->id,
                'sort_order' => $index + 1,
            ]);
        }

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->call('complete', $toComplete->id)
            ->call('unassign', $toUnassign->id);

        Livewire::actingAs($teacher)
            ->test(OtherTeacherRequests::class)
            ->set('managingRequestId', $managedByOtherTeacher->id)
            ->call('requeue');

        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'support_request_id' => $toComplete->id,
        ]);
        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'support_request_id' => $toUnassign->id,
        ]);
        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'support_request_id' => $managedByOtherTeacher->id,
        ]);
    }

    public function test_teacher_can_create_personal_note_from_active_request(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $student = User::factory()->create([
            'first_name' => 'Camille',
            'last_name' => 'Tremblay',
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
            'url' => 'https://moodle.example.test/course?table=[table]&section=[section]',
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'table_number' => '14',
            'moodle_tile_number' => 6,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee('Create personal note')
            ->assertSee('Camille Tremblay')
            ->assertSee('Francais - Tile 6')
            ->assertSee('https://moodle.example.test/course?table=14&amp;section=6', false)
            ->assertSee('aria-label="Open the subject link"', false)
            ->set('noteBodies.'.$supportRequest->id, 'Verifier le suivi demain.')
            ->call('savePersonalNote', $supportRequest->id)
            ->assertDispatched('personal-notes-count-updated');

        $this->assertDatabaseHas('personal_notes', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
            'body' => 'Verifier le suivi demain.',
            'archived_at' => null,
        ]);
    }

    public function test_teacher_cannot_create_personal_note_for_another_teacher_request(): void
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
            ->test(MyRequests::class)
            ->set('noteBodies.'.$supportRequest->id, 'Note impossible.')
            ->call('savePersonalNote', $supportRequest->id);

        $this->assertDatabaseMissing('personal_notes', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
        ]);
    }

    public function test_teacher_personal_notes_page_lists_only_own_unarchived_notes(): void
    {
        app(ApplicationSettings::class)->updateTimezone('America/Toronto');

        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 305']);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Mathematiques',
            'url' => 'https://moodle.example.test/course?table=[table]&section=[section]',
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
            'table_number' => '12',
            'moodle_tile_number' => 7,
        ]);
        $ownNote = PersonalNote::factory()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
            'body' => 'Relancer pour le laboratoire.',
            'created_at' => Carbon::parse('2026-06-22 14:30:00', 'UTC'),
        ]);
        $archivedNote = PersonalNote::factory()->archived()->create([
            'teacher_id' => $teacher->id,
            'body' => 'Note archivee.',
            'archived_at' => Carbon::parse('2026-01-15 20:45:00', 'UTC'),
        ]);
        $archivedLinkedNote = PersonalNote::factory()->archived()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
            'body' => 'Archive avec demande.',
        ]);
        $otherTeacherNote = PersonalNote::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'body' => 'Note autre enseignant.',
        ]);
        $otherTeacherArchivedNote = PersonalNote::factory()->archived()->create([
            'teacher_id' => $otherTeacher->id,
            'body' => 'Archive autre enseignant.',
        ]);

        $this
            ->actingAs($teacher)
            ->get(route('teacher.personal-notes.index'))
            ->assertOk()
            ->assertSee('Personal notes')
            ->assertSee('teacher.personal-notes', false);

        Livewire::actingAs($teacher)
            ->test(PersonalNotes::class)
            ->assertSee('Relancer pour le laboratoire.')
            ->assertSee('2026-06-22 10:30')
            ->assertDontSee('2026-06-22 14:30')
            ->assertSee('Request linked to this note')
            ->assertSee('Local 305')
            ->assertSee('Mathematiques - Tile 7')
            ->assertSee('https://moodle.example.test/course?table=12&amp;section=7', false)
            ->assertSee('aria-label="Open the subject link"', false)
            ->assertSee('Table')
            ->assertSee('Archived notes')
            ->assertSee('Note archivee.')
            ->assertSee('Archived 2026-01-15 15:45')
            ->assertDontSee('Archived 2026-01-15 20:45')
            ->assertSee('Archive avec demande.')
            ->assertSee('Delete permanently')
            ->assertSee('Delete all archived notes')
            ->assertDontSee('Note autre enseignant.')
            ->call('archive', $ownNote->id);

        $this->assertNotNull($ownNote->refresh()->archived_at);

        Livewire::actingAs($teacher)
            ->test(PersonalNotes::class)
            ->call('confirmDeleteArchived', $archivedNote->id)
            ->assertSet('archivedNotePendingDeletionId', $archivedNote->id)
            ->assertDispatched('open-modal')
            ->call('deleteArchived')
            ->assertDispatched('close-modal')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('personal_notes', [
            'id' => $archivedNote->id,
        ]);

        Livewire::actingAs($teacher)
            ->test(PersonalNotes::class)
            ->call('deleteAllArchived')
            ->assertDispatched('close-modal')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('personal_notes', [
            'id' => $archivedLinkedNote->id,
        ]);
        $this->assertDatabaseMissing('personal_notes', [
            'id' => $ownNote->id,
        ]);
        $this->assertDatabaseHas('personal_notes', [
            'id' => $otherTeacherNote->id,
        ]);
        $this->assertDatabaseHas('personal_notes', [
            'id' => $otherTeacherArchivedNote->id,
        ]);
    }

    public function test_teacher_can_create_standalone_personal_note(): void
    {
        $teacher = User::factory()->teacher()->create();

        Livewire::actingAs($teacher)
            ->test(PersonalNotes::class)
            ->assertSee('Add note')
            ->set('body', 'Preparer un rappel general.')
            ->call('create')
            ->assertSet('body', '')
            ->assertDispatched('close-modal')
            ->assertDispatched('personal-notes-count-updated');

        $this->assertDatabaseHas('personal_notes', [
            'teacher_id' => $teacher->id,
            'support_request_id' => null,
            'body' => 'Preparer un rappel general.',
            'archived_at' => null,
        ]);
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

    public function test_teacher_dashboard_page_title_uses_manual_active_request_order(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $firstStudent = User::factory()->create([
            'first_name' => 'First',
            'last_name' => 'Student',
        ]);
        $secondStudent = User::factory()->create([
            'first_name' => 'Second',
            'last_name' => 'Student',
        ]);
        $firstRequest = SupportRequest::factory()->create([
            'student_id' => $firstStudent->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(10),
        ]);
        $secondRequest = SupportRequest::factory()->create([
            'student_id' => $secondStudent->id,
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now()->subMinutes(20),
        ]);

        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $firstRequest->id,
            'sort_order' => 1,
        ]);
        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $teacher->id,
            'support_request_id' => $secondRequest->id,
            'sort_order' => 2,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(DashboardView::class)
            ->assertSet('pageTitle', '(0) - Second Student - LineUp');
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
        $activeRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'created_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->assertSee('History')
            ->assertSee($todayRequest->student->fullName())
            ->assertDontSee($oldRequest->student->fullName())
            ->assertDontSee($otherClassroomRequest->student->fullName())
            ->assertDontSee($activeRequest->student->fullName());
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

    public function test_teacher_can_restore_completed_history_request_as_assigned_to_self(): void
    {
        $teacher = User::factory()->teacher()->create(['place_new_requests_on_top' => true]);
        $previousTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Programmation',
        ]);
        $createdAt = now()->subHours(3);
        $supportRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'assigned_teacher_id' => $previousTeacher->id,
            'assigned_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'created_at' => $createdAt,
            'comment' => 'Conserver cette description',
            'table_number' => '12',
            'moodle_tile_number' => 7,
            'request_type' => 'Validation',
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->assertSee('Take')
            ->assertSee('Return to queue')
            ->call('restoreAndAssign', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->assertDontSee($supportRequest->student->fullName());

        $supportRequest->refresh();

        $this->assertSame($teacher->id, $supportRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_ASSIGNED, $supportRequest->status);
        $this->assertNotNull($supportRequest->assigned_at);
        $this->assertNull($supportRequest->completed_at);
        $this->assertNull($supportRequest->cancelled_by);
        $this->assertNull($supportRequest->cancel_reason);
        $this->assertSame('Conserver cette description', $supportRequest->comment);
        $this->assertSame('12', $supportRequest->table_number);
        $this->assertSame(7, $supportRequest->moodle_tile_number);
        $this->assertSame('Validation', $supportRequest->request_type);
        $this->assertSame($createdAt->toDateTimeString(), $supportRequest->created_at->toDateTimeString());
        $this->assertDatabaseHas('teacher_active_request_orders', [
            'teacher_id' => $teacher->id,
            'support_request_id' => $supportRequest->id,
        ]);
        $this->assertSame(1, app(SupportRequestChangeMarker::class)->current($classroom->id));

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee($supportRequest->student->fullName());
    }

    public function test_teacher_can_restore_completed_history_request_to_waiting_queue(): void
    {
        $teacher = User::factory()->teacher()->create();
        $previousTeacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $supportRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'assigned_teacher_id' => $previousTeacher->id,
            'assigned_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
        TeacherActiveRequestOrder::query()->create([
            'teacher_id' => $previousTeacher->id,
            'support_request_id' => $supportRequest->id,
            'sort_order' => 10,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->call('restoreToQueue', $supportRequest->id)
            ->assertDispatched('toast')
            ->assertDispatched('teacher-requests-updated')
            ->assertDontSee($supportRequest->student->fullName());

        $supportRequest->refresh();

        $this->assertNull($supportRequest->assigned_teacher_id);
        $this->assertNull($supportRequest->assigned_at);
        $this->assertSame(SupportRequest::STATUS_WAITING, $supportRequest->status);
        $this->assertNull($supportRequest->completed_at);
        $this->assertDatabaseMissing('teacher_active_request_orders', [
            'support_request_id' => $supportRequest->id,
        ]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSee($supportRequest->student->fullName());
    }

    public function test_teacher_can_restore_only_completed_requests_from_current_classroom_once(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $waitingRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);
        $cancelledRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_CANCELLED,
            'assigned_teacher_id' => null,
        ]);
        $otherClassroomRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $otherClassroom->id,
        ]);
        $completedRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(RequestHistory::class)
            ->assertSee($completedRequest->student->fullName())
            ->assertSee($cancelledRequest->student->fullName())
            ->assertSee('No actions available')
            ->assertDontSee($waitingRequest->student->fullName())
            ->call('restoreToQueue', $waitingRequest->id)
            ->assertDispatched('toast')
            ->call('restoreToQueue', $cancelledRequest->id)
            ->assertDispatched('toast')
            ->call('restoreToQueue', $otherClassroomRequest->id)
            ->assertDispatched('toast')
            ->call('restoreToQueue', $completedRequest->id)
            ->assertDispatched('teacher-requests-updated')
            ->call('restoreAndAssign', $completedRequest->id)
            ->assertDispatched('toast');

        $this->assertSame(SupportRequest::STATUS_WAITING, $waitingRequest->refresh()->status);
        $this->assertNull($waitingRequest->assigned_teacher_id);
        $this->assertSame(SupportRequest::STATUS_CANCELLED, $cancelledRequest->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_COMPLETED, $otherClassroomRequest->refresh()->status);
        $this->assertSame(SupportRequest::STATUS_WAITING, $completedRequest->refresh()->status);
        $this->assertNull($completedRequest->assigned_teacher_id);
    }
}
