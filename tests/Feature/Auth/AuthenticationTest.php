<?php

namespace Tests\Feature\Auth;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_login_auto_selects_classroom_from_active_request(): void
    {
        $user = User::factory()->create();
        $supportRequest = SupportRequest::factory()->create([
            'student_id' => $user->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertRedirect(route('student.dashboard', absolute: false))
            ->assertSessionHas('current_classroom_id', $supportRequest->classroom_id);
    }

    public function test_teacher_login_auto_selects_classroom_from_assigned_active_request(): void
    {
        $teacher = User::factory()->teacher()->create();
        $supportRequest = SupportRequest::factory()->create([
            'assigned_teacher_id' => $teacher->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $teacher->email,
            'password' => 'password',
        ]);

        $response
            ->assertRedirect(route('teacher.dashboard', absolute: false))
            ->assertSessionHas('current_classroom_id', $supportRequest->classroom_id);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
