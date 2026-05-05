<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_deactivate_classroom(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.classrooms.store'), [
            'name' => 'Local 301',
            'description' => 'Local de test',
            'is_active' => '1',
        ])->assertRedirect();

        $classroom = Classroom::query()->where('name', 'Local 301')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.classrooms.update', $classroom), [
            'name' => 'Local 302',
            'description' => 'Local renomme',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.classrooms.active', $classroom))->assertRedirect();

        $classroom->refresh();

        $this->assertSame('Local 302', $classroom->name);
        $this->assertFalse($classroom->is_active);
    }

    public function test_admin_can_create_update_and_deactivate_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 401']);
        $newClassroom = Classroom::factory()->create(['name' => 'Local 402']);

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'classroom_id' => $classroom->id,
            'name' => 'Sciences',
            'description' => 'Cours de sciences',
            'is_active' => '1',
        ])->assertRedirect();

        $subject = Subject::query()->where('name', 'Sciences')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), [
            'classroom_id' => $newClassroom->id,
            'name' => 'Science',
            'description' => 'Cours renomme',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.subjects.active', $subject))->assertRedirect();

        $subject->refresh();

        $this->assertSame($newClassroom->id, $subject->classroom_id);
        $this->assertSame('Science', $subject->name);
        $this->assertFalse($subject->is_active);
    }

    public function test_subject_name_is_unique_per_classroom(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
            'is_active' => '1',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'classroom_id' => $otherClassroom->id,
            'name' => 'Francais',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_can_delete_subject_without_deleting_support_request_history(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $supportRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.subjects.destroy', $subject))->assertRedirect();

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->assertDatabaseHas('support_requests', [
            'id' => $supportRequest->id,
            'subject_id' => null,
        ]);
    }

    public function test_admin_can_delete_classroom_without_deleting_support_request_history(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
        ]);
        $supportRequest = SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.classrooms.destroy', $classroom))->assertRedirect();

        $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'classroom_id' => null,
        ]);
        $this->assertDatabaseHas('support_requests', [
            'id' => $supportRequest->id,
            'classroom_id' => null,
        ]);
    }
}
