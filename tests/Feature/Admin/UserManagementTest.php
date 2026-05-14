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

    public function test_student_can_not_access_user_management(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_teacher_can_access_user_management(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users')
            ->assertDontSee('name="is_admin"', false);
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

    public function test_admin_user_list_defaults_to_all_statuses_and_paginates_results(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(101)->create([
            'is_active' => true,
        ]);
        $inactive = User::factory()->create([
            'name' => 'Inactive Person',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSeeText('103 users shown')
            ->assertSee('value="all" selected', false)
            ->assertSee('page=2', false);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeText($inactive->name);
    }

    public function test_admin_user_list_can_search_by_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $byName = User::factory()->create([
            'name' => 'Alice Recherche',
            'email' => 'alice@example.com',
            'is_active' => true,
        ]);
        $byEmail = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob.recherche@example.com',
            'is_active' => true,
        ]);
        $hidden = User::factory()->create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'search' => 'recherche',
            'status' => 'active',
            'role' => 'all',
        ]));

        $response
            ->assertOk()
            ->assertSeeText($byName->name)
            ->assertSeeText($byEmail->email)
            ->assertDontSeeText($hidden->name);
    }

    public function test_admin_user_list_can_filter_by_status_and_role(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveTeacher = User::factory()->teacher()->create([
            'name' => 'Teacher Inactive',
            'is_active' => false,
        ]);
        $activeTeacher = User::factory()->teacher()->create([
            'name' => 'Teacher Active',
            'is_active' => true,
        ]);
        $inactiveStudent = User::factory()->create([
            'name' => 'Student Inactive',
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index', [
            'status' => 'inactive',
            'role' => 'teacher',
        ]));

        $response
            ->assertOk()
            ->assertSeeText($inactiveTeacher->name)
            ->assertDontSeeText($activeTeacher->name)
            ->assertDontSeeText($inactiveStudent->name);
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

    public function test_admin_can_remove_admin_role_from_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.roles', $user), [
            'is_teacher' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
    }

    public function test_teacher_can_not_assign_admin_role_to_themselves(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.roles', $teacher), [
            'is_teacher' => '1',
            'is_admin' => '1',
        ])->assertForbidden();

        $this->assertFalse($teacher->refresh()->is_admin);
    }

    public function test_teacher_can_not_assign_admin_role_to_another_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'is_student' => '1',
            'is_admin' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])->assertForbidden();

        $this->assertFalse($user->refresh()->is_admin);
    }

    public function test_teacher_can_not_create_admin_user(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->post(route('admin.users.store'), [
            'name' => 'Created Admin',
            'email' => 'created-admin@example.com',
            'password' => 'password',
            'is_teacher' => '1',
            'is_admin' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'created-admin@example.com',
        ]);
    }

    public function test_teacher_can_not_remove_admin_role_from_existing_admin(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => true,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.roles', $admin), [
            'is_teacher' => '1',
            'is_admin' => '0',
        ])->assertForbidden();

        $admin->refresh();

        $this->assertTrue($admin->is_teacher);
        $this->assertTrue($admin->is_admin);
    }

    public function test_teacher_can_update_allowed_non_admin_roles(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $response = $this->actingAs($teacher)->patch(route('admin.users.roles', $user), [
            'is_teacher' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
    }

    public function test_teacher_editing_admin_user_preserves_admin_role_when_admin_field_is_absent(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create([
            'name' => 'Original Admin',
            'email' => 'original-admin@example.com',
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $response = $this->actingAs($teacher)->patch(route('admin.users.update', $admin), [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect();

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertSame('updated-admin@example.com', $admin->email);
        $this->assertFalse($admin->is_student);
        $this->assertFalse($admin->is_teacher);
        $this->assertTrue($admin->is_admin);
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
