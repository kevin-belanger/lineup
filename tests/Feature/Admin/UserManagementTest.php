<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Marie',
            'email' => 'marie@example.com',
            'password' => 'password',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'marie@example.com')->firstOrFail();

        $this->assertSame('Marie', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertFalse($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_approved);
        $this->assertSame($admin->id, $user->approved_by);
    }

    public function test_admin_can_update_user_information_roles_and_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->teacher()->create([
            'name' => 'Ancien',
            'email' => 'ancien@example.com',
            'is_active' => false,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Nouveau',
            'email' => 'nouveau@example.com',
            'is_student' => '1',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame('Nouveau', $user->name);
        $this->assertSame('nouveau@example.com', $user->email);
        $this->assertTrue($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->is_approved);
        $this->assertSame($admin->id, $user->approved_by);
    }

    public function test_user_email_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'pris@example.com']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Doublon',
            'email' => 'pris@example.com',
            'password' => 'password',
            'is_student' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_user_password_without_changing_other_information(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->teacher()->create([
            'name' => 'Jean',
            'email' => 'jean@example.com',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.password', $user), [
            'password' => 'Nouveau-Mot2Passe!',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame('Jean', $user->name);
        $this->assertSame('jean@example.com', $user->email);
        $this->assertTrue($user->is_teacher);
        $this->assertTrue(Hash::check('Nouveau-Mot2Passe!', $user->password));
    }

    public function test_user_password_can_not_be_empty(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.password', $user), [
            'password' => '',
        ])->assertSessionHasErrors('password');
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
