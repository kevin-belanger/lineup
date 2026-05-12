<?php

namespace Tests\Feature;

use App\Models\Classroom;
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
            'api.github.com/repos/*/*/tags*' => Http::response([
                ['name' => 'v0.0.1'],
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
            ->assertSee('Users')
            ->assertSee('Rooms')
            ->assertSee('Subjects')
            ->assertSee('Settings');
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
