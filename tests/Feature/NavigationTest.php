<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\PersonalNote;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.github.com/repos/*/*/releases/latest' => Http::response([
                'tag_name' => 'v0.0.1',
            ]),
        ]);
    }

    public function test_navigation_hides_dashboard_and_admin_menu_for_non_admin_users(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertDontSee('Dashboard')
            ->assertDontSee('Administration')
            ->assertDontSee('Users')
            ->assertDontSee('Rooms')
            ->assertDontSee('Subjects');
    }

    public function test_student_history_is_available_from_main_navigation_only(): void
    {
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $response = $this
            ->actingAs($student)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSee('History')
            ->assertSee('Main menu')
            ->assertSee('ms-3 space-y-1', false)
            ->assertSee(route('student.history'), false);

        $this->assertSame(2, substr_count($response->getContent(), route('student.history')));
    }

    public function test_admin_navigation_shows_admin_dropdown_links_without_dashboard_link(): void
    {
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertDontSee('Dashboard')
            ->assertSee('Administration')
            ->assertSee('Statistics')
            ->assertSee('Users')
            ->assertSee('Rooms')
            ->assertSee('Subjects')
            ->assertSee('Settings')
            ->assertSeeInOrder(['Users', 'Rooms', 'Subjects', 'Statistics', 'Settings'])
            ->assertSee('border-t border-gray-100', false)
            ->assertSee('border-t border-gray-200', false);
    }

    public function test_teacher_navigation_shows_administration_links_without_settings(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);
        PersonalNote::factory()->count(2)->create([
            'teacher_id' => $teacher->id,
        ]);
        PersonalNote::factory()->archived()->create([
            'teacher_id' => $teacher->id,
        ]);
        PersonalNote::factory()->create();

        $response = $this->actingAs($teacher)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSeeText('Personal notes')
            ->assertSee('data-personal-notes-count', false)
            ->assertSee('>2</span>', false)
            ->assertSee(route('teacher.personal-notes.index'), false)
            ->assertSeeText('Administration')
            ->assertSeeText('Main menu')
            ->assertSee('ms-3 space-y-1', false)
            ->assertSeeText('Statistics')
            ->assertSeeText('Users')
            ->assertSeeText('Rooms')
            ->assertSeeText('Subjects')
            ->assertSeeInOrder(['Users', 'Rooms', 'Subjects', 'Statistics'])
            ->assertDontSeeText('Settings')
            ->assertDontSee(route('admin.settings.edit'), false);
    }

    public function test_teacher_personal_notes_badge_is_hidden_when_count_is_zero(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSeeText('Personal notes')
            ->assertSee('data-personal-notes-count', false)
            ->assertSee('style="display: none;"', false);
    }

    public function test_teacher_navigation_shows_waiting_request_badge_outside_teacher_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);
        $classroom = Classroom::factory()->create();

        SupportRequest::factory()->count(3)->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);
        SupportRequest::factory()->create([
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        $response = $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSee('data-teacher-waiting-requests-count', false)
            ->assertSee('>3</span>', false)
            ->assertSee('wire:poll.2s.keep-alive="check"', false);
    }

    public function test_teacher_navigation_waiting_request_badge_is_hidden_without_selected_classroom(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);

        SupportRequest::factory()->create([
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        $response = $this->actingAs($teacher)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSee('data-teacher-waiting-requests-count', false)
            ->assertSee('style="display: none;"', false)
            ->assertDontSee('wire:poll.2s.keep-alive="check"', false);
    }

    public function test_teacher_navigation_waiting_request_watcher_is_not_rendered_on_teacher_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);
        $classroom = Classroom::factory()->create();

        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_WAITING,
            'assigned_teacher_id' => null,
        ]);

        $response = $this
            ->actingAs($teacher)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('teacher.dashboard'));

        $response
            ->assertOk()
            ->assertSee('data-teacher-waiting-requests-count', false)
            ->assertSee('style="display: none;"', false);
    }

    public function test_user_menu_keeps_language_choice_out_of_navigation(): void
    {
        $user = User::factory()->create();
        $classroom = Classroom::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['current_classroom_id' => $classroom->id])
            ->get(route('student.dashboard'));

        $response
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Log Out')
            ->assertDontSee('Use application default')
            ->assertDontSee(route('profile.language.update'), false);
    }

    public function test_admin_dashboard_shows_settings_card(): void
    {
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Settings')
            ->assertSee(route('admin.settings.edit'), false);
    }

    public function test_teacher_admin_dashboard_hides_settings_card(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeText('Users')
            ->assertSeeText('Rooms')
            ->assertSeeText('Subjects')
            ->assertDontSeeText('Settings')
            ->assertDontSee(route('admin.settings.edit'), false);
    }

    public function test_admin_subpages_show_breadcrumb_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Breadcrumb')
            ->assertSee('Administration')
            ->assertSee('Users')
            ->assertSee(route('admin.dashboard'), false);

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Administration')
            ->assertSee('Settings')
            ->assertSee(route('admin.dashboard'), false);
    }

    public function test_dashboard_logo_destination_prioritizes_teacher_before_admin(): void
    {
        $user = User::factory()->create([
            'is_student' => false,
            'is_teacher' => true,
            'is_admin' => true,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('teacher.dashboard'));
    }
}
