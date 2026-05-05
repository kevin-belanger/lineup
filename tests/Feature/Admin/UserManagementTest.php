<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_user_can_not_access_student_space(): void
    {
        $user = User::factory()->pendingApproval()->create();

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertRedirect(route('approval.pending'));
    }

    public function test_non_admin_can_not_access_user_management(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_approve_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pendingApproval()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.approve', $user));

        $response->assertRedirect();

        $user->refresh();

        $this->assertTrue($user->is_approved);
        $this->assertTrue($user->approved_at !== null);
        $this->assertTrue($user->approved_by === $admin->id);
    }

    public function test_admin_can_update_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.roles', $user), [
            'is_teacher' => '1',
            'is_admin' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertTrue($user->is_admin);
    }

    public function test_admin_can_deactivate_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.active', $user));

        $response->assertRedirect();

        $this->assertFalse($user->refresh()->is_active);
    }
}
