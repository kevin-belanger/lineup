<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertDontSee('Utilisateurs')
            ->assertDontSee('Locaux')
            ->assertDontSee('Matieres');
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
            ->assertSee('Utilisateurs')
            ->assertSee('Locaux')
            ->assertSee('Matieres');
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
