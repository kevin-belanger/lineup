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

    public function test_pending_users_show_quick_approve_action_in_user_list(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pendingApproval()->create([
            'first_name' => 'Pending',
            'last_name' => 'Person',
        ]);
        $approved = User::factory()->create([
            'first_name' => 'Approved',
            'last_name' => 'Person',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response
            ->assertOk()
            ->assertSeeInOrder([
                $pending->fullName(),
                'Approve',
                'Edit',
                $approved->fullName(),
            ])
            ->assertSee(route('admin.users.approve', $pending), false)
            ->assertDontSee(route('admin.users.approve', $approved), false);
    }

    public function test_quick_approve_only_updates_approval_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pendingApproval()->create([
            'first_name' => 'Marie',
            'last_name' => 'Dubois',
            'email' => 'marie.dubois@example.com',
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
            'is_active' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.approve', $user))
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('Marie', $user->first_name);
        $this->assertSame('Dubois', $user->last_name);
        $this->assertSame('marie.dubois@example.com', $user->email);
        $this->assertTrue($user->is_student);
        $this->assertFalse($user->is_teacher);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->is_approved);
        $this->assertNotNull($user->approved_at);
        $this->assertSame($admin->id, $user->approved_by);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee(route('admin.users.approve', $user), false);
    }

    public function test_student_can_not_quick_approve_user(): void
    {
        $student = User::factory()->create();
        $user = User::factory()->pendingApproval()->create();

        $this->actingAs($student)->patch(route('admin.users.approve', $user))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->is_approved);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'first_name' => 'Marie',
            'email' => 'marie@example.com',
            'password' => 'password',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('open_create_panel', 'users')
            ->assertSessionMissing('_old_input');

        $user = User::query()->where('email', 'marie@example.com')->firstOrFail();

        $this->assertSame('Marie', $user->fullName());
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
            'first_name' => 'Inactive Person',
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
            ->assertSeeText($inactive->fullName());
    }

    public function test_admin_user_list_can_search_by_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $byName = User::factory()->create([
            'first_name' => 'Alice Recherche',
            'email' => 'alice@example.com',
            'is_active' => true,
        ]);
        $byEmail = User::factory()->create([
            'first_name' => 'Bob',
            'email' => 'bob.recherche@example.com',
            'is_active' => true,
        ]);
        $hidden = User::factory()->create([
            'first_name' => 'Charlie',
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
            ->assertSeeText($byName->fullName())
            ->assertSeeText($byEmail->email)
            ->assertDontSeeText($hidden->fullName());
    }

    public function test_admin_user_list_can_filter_by_status_and_role(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveTeacher = User::factory()->teacher()->create([
            'first_name' => 'Teacher Inactive',
            'is_active' => false,
        ]);
        $activeTeacher = User::factory()->teacher()->create([
            'first_name' => 'Teacher Active',
            'is_active' => true,
        ]);
        $inactiveStudent = User::factory()->create([
            'first_name' => 'Student Inactive',
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
            ->assertSeeText($inactiveTeacher->fullName())
            ->assertDontSeeText($activeTeacher->fullName())
            ->assertDontSeeText($inactiveStudent->fullName());
    }

    public function test_admin_can_update_user_information_roles_and_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->teacher()->create([
            'first_name' => 'Ancien',
            'email' => 'ancien@example.com',
            'is_active' => false,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'first_name' => 'Nouveau',
            'email' => 'nouveau@example.com',
            'is_student' => '1',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame('Nouveau', $user->fullName());
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
            'first_name' => 'Doublon',
            'email' => 'pris@example.com',
            'password' => 'password',
            'is_student' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'This email address is already in use.',
            ])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_failed_user_creation_reopens_create_panel_with_old_input(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'pris@example.com']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->followingRedirects()
            ->post(route('admin.users.store'), [
                'create_panel' => 'create-user',
                'first_name' => 'Doublon',
                'email' => 'pris@example.com',
                'password' => 'password',
                'is_student' => '1',
                'is_active' => '1',
                'is_approved' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('This email address is already in use.')
            ->assertSee('value="Doublon"', false)
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee('pris@example.com');
    }

    public function test_user_edit_validation_errors_do_not_reopen_create_panel(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'pris@example.com']);
        $user = User::factory()->create(['email' => 'ancien@example.com']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.index'))
            ->followingRedirects()
            ->patch(route('admin.users.update', $user), [
                'first_name' => 'Ancien',
                'email' => 'pris@example.com',
                'is_student' => '1',
                'is_active' => '1',
                'is_approved' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('This email address is already in use.')
            ->assertDontSee('x-data="{ open: true }"', false);
    }

    public function test_admin_can_update_user_password_without_changing_other_information(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->teacher()->create([
            'first_name' => 'Jean',
            'last_name' => null,
            'email' => 'jean@example.com',
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.users.password', $user), [
            'password' => 'Nouveau-Mot2Passe!',
        ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertSame('Jean', $user->fullName());
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
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'The password field is required.',
            ])
            ->assertSessionDoesntHaveErrors();
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

    public function test_teacher_can_add_student_role_to_themselves(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $teacher), [
            'first_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'is_student' => '1',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])
            ->assertRedirect()
            ->assertSessionMissing('toast');

        $teacher->refresh();

        $this->assertTrue($teacher->is_student);
        $this->assertTrue($teacher->is_teacher);
        $this->assertFalse($teacher->is_admin);
    }

    public function test_teacher_can_remove_student_role_from_themselves(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => true,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $teacher), [
            'first_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])
            ->assertRedirect()
            ->assertSessionMissing('toast');

        $teacher->refresh();

        $this->assertFalse($teacher->is_student);
        $this->assertTrue($teacher->is_teacher);
        $this->assertFalse($teacher->is_admin);
    }

    public function test_teacher_can_not_remove_their_own_teacher_role(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_student' => true,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $teacher), [
            'first_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'is_student' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'You cannot remove your own teacher role.',
            ]);

        $teacher->refresh();

        $this->assertTrue($teacher->is_student);
        $this->assertTrue($teacher->is_teacher);
        $this->assertFalse($teacher->is_admin);
    }

    public function test_teacher_can_not_assign_admin_role_to_another_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $user), [
            'first_name' => $user->fullName(),
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
            'first_name' => 'Created Admin',
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

    public function test_teacher_can_assign_student_role_to_another_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_student' => false,
            'is_teacher' => true,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.roles', $user), [
            'is_student' => '1',
            'is_teacher' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertTrue($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
    }

    public function test_teacher_can_remove_student_role_from_another_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_student' => true,
            'is_teacher' => true,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.roles', $user), [
            'is_teacher' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_student);
        $this->assertTrue($user->is_teacher);
        $this->assertFalse($user->is_admin);
    }

    public function test_teacher_can_remove_teacher_role_from_another_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->teacher()->create([
            'is_student' => true,
            'is_admin' => false,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.roles', $user), [
            'is_student' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertTrue($user->is_student);
        $this->assertFalse($user->is_teacher);
        $this->assertFalse($user->is_admin);
    }

    public function test_user_can_not_deactivate_themselves(): void
    {
        $teacher = User::factory()->teacher()->create([
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $teacher), [
            'first_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'is_teacher' => '1',
            'is_active' => '0',
            'is_approved' => '1',
        ])->assertRedirect();

        $this->assertTrue($teacher->refresh()->is_active);
    }

    public function test_admin_can_not_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.active', $admin))
            ->assertRedirect();

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_teacher_can_not_deactivate_admin(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create([
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.active', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_teacher_can_deactivate_non_admin_user(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.active', $user))
            ->assertRedirect();

        $this->assertFalse($user->refresh()->is_active);
    }

    public function test_admin_can_deactivate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create([
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.active', $otherAdmin))
            ->assertRedirect();

        $this->assertFalse($otherAdmin->refresh()->is_active);
    }

    public function test_admin_can_deactivate_teacher_or_student(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create([
            'is_active' => true,
        ]);
        $student = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.active', $teacher))
            ->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.users.active', $student))
            ->assertRedirect();

        $this->assertFalse($teacher->refresh()->is_active);
        $this->assertFalse($student->refresh()->is_active);
    }

    public function test_user_can_not_be_assigned_teacher_role_unless_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pendingApproval()->create([
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.roles', $user), [
            'is_teacher' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_approved);
        $this->assertFalse($user->is_teacher);
    }

    public function test_user_can_not_be_assigned_admin_role_unless_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pendingApproval()->create([
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.roles', $user), [
            'is_admin' => '1',
        ])->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_approved);
        $this->assertFalse($user->is_admin);
    }

    public function test_teacher_or_admin_user_can_not_be_changed_to_unapproved(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $teacher), [
            'first_name' => $teacher->fullName(),
            'email' => $teacher->email,
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '0',
        ])->assertRedirect();

        $teacher->refresh();

        $this->assertTrue($teacher->is_teacher);
        $this->assertTrue($teacher->is_approved);
    }

    public function test_admin_can_not_unapprove_themselves_while_admin(): void
    {
        $admin = User::factory()->admin()->create([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'first_name' => $admin->fullName(),
            'email' => $admin->email,
            'is_admin' => '1',
            'is_active' => '1',
            'is_approved' => '0',
        ])->assertRedirect();

        $admin->refresh();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_approved);
    }

    public function test_teacher_can_not_create_unapproved_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->post(route('admin.users.store'), [
            'first_name' => 'Unapproved Teacher',
            'email' => 'unapproved-teacher@example.com',
            'password' => 'password',
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '0',
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'email' => 'unapproved-teacher@example.com',
        ]);
    }

    public function test_teacher_can_not_update_user_into_unapproved_teacher_state(): void
    {
        $teacher = User::factory()->teacher()->create();
        $user = User::factory()->create([
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
            'is_approved' => true,
        ]);

        $this->actingAs($teacher)->patch(route('admin.users.update', $user), [
            'first_name' => $user->fullName(),
            'email' => $user->email,
            'is_teacher' => '1',
            'is_active' => '1',
            'is_approved' => '0',
        ])->assertRedirect();

        $user->refresh();

        $this->assertFalse($user->is_teacher);
        $this->assertTrue($user->is_approved);
    }

    public function test_teacher_editing_admin_user_preserves_admin_role_when_admin_field_is_absent(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create([
            'first_name' => 'Original Admin',
            'email' => 'original-admin@example.com',
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $response = $this->actingAs($teacher)->patch(route('admin.users.update', $admin), [
            'first_name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
            'is_active' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect();

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->fullName());
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

    public function test_admin_can_manage_own_lower_roles_while_keeping_admin_role(): void
    {
        $admin = User::factory()->admin()->create([
            'is_student' => false,
            'is_teacher' => false,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'first_name' => $admin->fullName(),
            'email' => $admin->email,
            'is_student' => '1',
            'is_teacher' => '1',
            'is_admin' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])->assertRedirect();

        $admin->refresh();

        $this->assertTrue($admin->is_student);
        $this->assertTrue($admin->is_teacher);
        $this->assertTrue($admin->is_admin);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'first_name' => $admin->fullName(),
            'email' => $admin->email,
            'is_admin' => '1',
            'is_active' => '1',
            'is_approved' => '1',
        ])->assertRedirect();

        $admin->refresh();

        $this->assertFalse($admin->is_student);
        $this->assertFalse($admin->is_teacher);
        $this->assertTrue($admin->is_admin);
    }

    public function test_student_only_user_can_not_remove_their_own_student_role(): void
    {
        $student = User::factory()->create([
            'is_student' => true,
            'is_teacher' => false,
            'is_admin' => false,
        ]);

        $this->actingAs($student)->patch(route('admin.users.roles', $student), [])
            ->assertForbidden();
    }
}
