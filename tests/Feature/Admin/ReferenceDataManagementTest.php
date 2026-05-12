<?php

namespace Tests\Feature\Admin;

use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_admin_classroom_list_can_search_and_filter_classrooms(): void
    {
        $admin = User::factory()->admin()->create();
        $network = Classroom::factory()->create([
            'name' => 'Local reseau',
            'description' => 'Salle reseautique',
            'is_active' => true,
        ]);
        $evaluation = Classroom::factory()->create([
            'name' => 'Local evaluation',
            'description' => 'Examens',
            'is_active' => false,
        ]);
        $lab = Classroom::factory()->create([
            'name' => 'Laboratoire',
            'description' => 'Postes Windows',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.index', ['search' => 'examens']))
            ->assertOk()
            ->assertSeeText($evaluation->name)
            ->assertDontSeeText($network->name)
            ->assertDontSeeText($lab->name);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSeeText($evaluation->name)
            ->assertDontSeeText($network->name)
            ->assertDontSeeText($lab->name);
    }

    public function test_admin_classroom_list_preserves_filters_when_paginating(): void
    {
        $admin = User::factory()->admin()->create();

        Classroom::factory()->count(21)->create([
            'is_active' => true,
        ]);
        Classroom::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('status=active', false)
            ->assertSee('page=2', false);
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
            'url' => 'https://moodle.example.com/course/view.php?id=12&section=[section]&table=[table]',
            'is_active' => '1',
        ])->assertRedirect();

        $subject = Subject::query()->where('name', 'Sciences')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), [
            'classroom_id' => $newClassroom->id,
            'name' => 'Science',
            'description' => 'Cours renomme',
            'url' => 'https://moodle.example.com/course/view.php?id=13',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.subjects.active', $subject))->assertRedirect();

        $subject->refresh();

        $this->assertSame($newClassroom->id, $subject->classroom_id);
        $this->assertSame('Science', $subject->name);
        $this->assertSame('https://moodle.example.com/course/view.php?id=13', $subject->url);
        $this->assertFalse($subject->is_active);
    }

    public function test_subject_url_must_be_valid_after_replacing_supported_variables(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'classroom_id' => $classroom->id,
            'name' => 'Informatique',
            'url' => 'https://moodle.example.com/course/view.php?id=12&section=[section]&table=[table]',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'classroom_id' => $classroom->id,
            'name' => 'Robotique',
            'url' => 'pas une url',
            'is_active' => '1',
        ])->assertSessionHasErrors('url');
    }

    public function test_admin_subject_list_can_search_and_filter_subjects(): void
    {
        $admin = User::factory()->admin()->create();
        $localA = Classroom::factory()->create(['name' => 'Local A']);
        $localB = Classroom::factory()->create(['name' => 'Local B']);
        $math = Subject::factory()->create([
            'classroom_id' => $localA->id,
            'name' => 'Mathematique',
            'description' => 'Algebre',
            'is_active' => true,
        ]);
        $robotics = Subject::factory()->create([
            'classroom_id' => $localA->id,
            'name' => 'Informatique',
            'description' => 'Robotique avancee',
            'is_active' => true,
        ]);
        $history = Subject::factory()->create([
            'classroom_id' => $localB->id,
            'name' => 'Histoire',
            'description' => 'Culture generale',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index', ['search' => 'robotique']))
            ->assertOk()
            ->assertSeeText($robotics->name)
            ->assertDontSeeText($math->name)
            ->assertDontSeeText($history->name);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index', ['classroom' => (string) $localB->id]))
            ->assertOk()
            ->assertSeeText($history->name)
            ->assertDontSeeText($math->name)
            ->assertDontSeeText($robotics->name);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSeeText($history->name)
            ->assertDontSeeText($math->name)
            ->assertDontSeeText($robotics->name);
    }

    public function test_admin_subject_list_preserves_filters_when_paginating(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();

        Subject::factory()->count(21)->create([
            'classroom_id' => $classroom->id,
            'is_active' => true,
        ]);
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subjects.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('status=active', false)
            ->assertSee('page=2', false);
    }

    public function test_subject_url_is_generated_on_teacher_request_cards(): void
    {
        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'url' => 'https://moodle.example.com/course/view.php?id=12&section=[section]&table=[table]',
        ]);
        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'moodle_tile_number' => 3,
            'table_number' => '5',
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSee('Open the subject link')
            ->assertSee('https://moodle.example.com/course/view.php?id=12&amp;section=3&amp;table=5', false);
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
