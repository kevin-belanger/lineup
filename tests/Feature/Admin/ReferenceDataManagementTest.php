<?php

namespace Tests\Feature\Admin;

use App\Livewire\Teacher\MyRequests;
use App\Livewire\Teacher\WaitingQueue;
use App\Models\Classroom;
use App\Models\PublicDisplaySlug;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ApplicationSettings;
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
        ])
            ->assertRedirect()
            ->assertSessionHas('open_create_panel', 'classrooms')
            ->assertSessionMissing('_old_input');

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

    public function test_admin_can_enable_disable_and_regenerate_classroom_public_page(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create([
            'public_enabled' => false,
            'public_slug' => null,
        ]);
        $firstSlug = PublicDisplaySlug::reserveUnique()->slug;

        $this->actingAs($admin)->patch(route('admin.classrooms.update', $classroom), [
            'name' => $classroom->name,
            'description' => $classroom->description,
            'is_active' => '1',
            'public_enabled' => '1',
            'public_slug' => $firstSlug,
        ])->assertRedirect();

        $classroom->refresh();

        $this->assertTrue($classroom->public_enabled);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{5}$/', $firstSlug);
        $this->assertSame($firstSlug, $classroom->public_slug);
        $this->get(route('public-display.show', $firstSlug))->assertOk();
        $secondSlug = PublicDisplaySlug::reserveUnique()->slug;

        $this->actingAs($admin)->patch(route('admin.classrooms.update', $classroom), [
            'name' => $classroom->name,
            'description' => $classroom->description,
            'is_active' => '1',
            'public_enabled' => '1',
            'public_slug' => $secondSlug,
        ])->assertRedirect();

        $classroom->refresh();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{5}$/', $secondSlug);
        $this->assertNotSame($firstSlug, $secondSlug);
        $this->assertSame($secondSlug, $classroom->public_slug);
        $this->get(route('public-display.show', $firstSlug))->assertNotFound();
        $this->get(route('public-display.show', $secondSlug))->assertOk();

        $this->actingAs($admin)->patch(route('admin.classrooms.update', $classroom), [
            'name' => $classroom->name,
            'description' => $classroom->description,
            'is_active' => '1',
            'public_enabled' => '0',
        ])->assertRedirect();

        $classroom->refresh();

        $this->assertFalse($classroom->public_enabled);
        $this->assertNull($classroom->public_slug);
        $this->assertDatabaseHas('public_display_slugs', ['slug' => $firstSlug]);
        $this->assertDatabaseHas('public_display_slugs', ['slug' => $secondSlug]);
        $this->get(route('public-display.show', $secondSlug))->assertNotFound();
    }

    public function test_admin_can_reserve_classroom_public_slug_without_updating_classroom(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create([
            'public_enabled' => false,
            'public_slug' => null,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.classrooms.public-slugs.store'))
            ->assertOk()
            ->assertJsonStructure(['slug', 'url']);

        $slug = $response->json('slug');

        $this->assertMatchesRegularExpression('/^[a-z0-9]{5}$/', $slug);
        $this->assertSame(route('public-display.show', $slug), $response->json('url'));
        $this->assertDatabaseHas('public_display_slugs', ['slug' => $slug]);
        $this->assertFalse($classroom->refresh()->public_enabled);
        $this->assertNull($classroom->public_slug);
        $this->get(route('public-display.show', $slug))->assertNotFound();
    }

    public function test_teacher_can_access_classroom_and_subject_management(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('Rooms');

        $this->actingAs($teacher)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('Subjects');
    }

    public function test_admin_classroom_management_includes_rooms_without_active_subjects(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create([
            'name' => 'Admin-visible room without subjects',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSeeText($classroom->name);
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

    public function test_failed_classroom_creation_reopens_create_panel_with_old_input(): void
    {
        $admin = User::factory()->admin()->create();
        Classroom::factory()->create(['name' => 'Local 303']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.classrooms.index'))
            ->followingRedirects()
            ->post(route('admin.classrooms.store'), [
                'create_panel' => 'create-classroom',
                'name' => 'Local 303',
                'description' => 'Create form description',
                'is_active' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('A room with this name already exists.')
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee('Create form description');
    }

    public function test_classroom_edit_validation_errors_do_not_reopen_create_panel(): void
    {
        $admin = User::factory()->admin()->create();
        Classroom::factory()->create(['name' => 'Local 303']);
        $classroom = Classroom::factory()->create(['name' => 'Local 304']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.classrooms.index'))
            ->followingRedirects()
            ->patch(route('admin.classrooms.update', $classroom), [
                'name' => 'Local 303',
                'description' => 'Edit form description',
                'is_active' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('A room with this name already exists.')
            ->assertDontSee('x-data="{ open: true }"', false);
    }

    public function test_admin_can_create_update_and_deactivate_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 401']);
        $newClassroom = Classroom::factory()->create(['name' => 'Local 402']);

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$classroom->id],
            'name' => 'Sciences',
            'description' => 'Cours de sciences',
            'url' => 'https://moodle.example.com/course/view.php?id=12&section=[section]&table=[table]',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('open_create_panel', 'subjects')
            ->assertSessionMissing('_old_input');

        $subject = Subject::query()->where('name', 'Sciences')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), [
            'local_ids' => [$newClassroom->id],
            'name' => 'Science',
            'description' => 'Cours renomme',
            'url' => 'https://moodle.example.com/course/view.php?id=13',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.subjects.active', $subject))->assertRedirect();

        $subject->refresh();

        $this->assertSame($newClassroom->id, $subject->classroom_id);
        $this->assertSame([$newClassroom->id], $subject->locals()->pluck('classrooms.id')->all());
        $this->assertSame('Science', $subject->name);
        $this->assertSame('https://moodle.example.com/course/view.php?id=13', $subject->url);
        $this->assertFalse($subject->is_active);
    }

    public function test_admin_can_create_subject_with_no_classroom(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'name' => 'General help',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $subject = Subject::query()->where('name', 'General help')->firstOrFail();

        $this->assertNull($subject->classroom_id);
        $this->assertSame(0, $subject->locals()->count());
    }

    public function test_admin_can_create_subject_with_multiple_classrooms(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$classroom->id, $otherClassroom->id],
            'name' => 'Shared subject',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $subject = Subject::query()->where('name', 'Shared subject')->firstOrFail();

        $this->assertSame($classroom->id, $subject->classroom_id);
        $this->assertEqualsCanonicalizing(
            [$classroom->id, $otherClassroom->id],
            $subject->locals()->pluck('classrooms.id')->all(),
        );
    }

    public function test_subject_url_must_be_valid_after_replacing_supported_variables(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$classroom->id],
            'name' => 'Informatique',
            'url' => 'https://moodle.example.com/course/view.php?id=12&section=[section]&table=[table]',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$classroom->id],
            'name' => 'Robotique',
            'url' => 'pas une url',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'The URL must be valid.',
            ])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_failed_subject_creation_reopens_create_panel_with_old_input(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.subjects.index'))
            ->followingRedirects()
            ->post(route('admin.subjects.store'), [
                'create_panel' => 'create-subject',
                'local_ids' => [$classroom->id],
                'name' => 'Francais',
                'description' => 'Create subject description',
                'url' => 'https://moodle.example.com/course/view.php?id=12',
                'is_active' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee('A subject with this name already exists.')
            ->assertSee('Create subject description')
            ->assertSee('moodle.example.com');
    }

    public function test_subject_edit_validation_errors_do_not_reopen_create_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Mathematique',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.subjects.index'))
            ->followingRedirects()
            ->patch(route('admin.subjects.update', $subject), [
                'local_ids' => [$classroom->id],
                'name' => 'Francais',
                'description' => 'Edit subject description',
                'is_active' => '1',
            ]);

        $response
            ->assertOk()
            ->assertSee('A subject with this name already exists.')
            ->assertDontSee('x-data="{ open: true }"', false);
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
            ->assertSee('https://moodle.example.com/course/view.php?id=12&amp;section=3&amp;table=5', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_course_url_links_can_reuse_a_named_browser_tab_on_teacher_request_cards(): void
    {
        app(ApplicationSettings::class)->updateReuseCourseUrlTab(true);

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

        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'assigned_teacher_id' => $teacher->id,
            'moodle_tile_number' => 4,
            'table_number' => '6',
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        session(['current_classroom_id' => $classroom->id]);

        Livewire::actingAs($teacher)
            ->test(WaitingQueue::class)
            ->assertSee('target="lineup_course_url"', false)
            ->assertDontSee('target="_blank"', false)
            ->assertDontSee('rel="noopener noreferrer"', false);

        Livewire::actingAs($teacher)
            ->test(MyRequests::class)
            ->assertSee('target="lineup_course_url"', false)
            ->assertDontSee('target="_blank"', false)
            ->assertDontSee('rel="noopener noreferrer"', false);
    }

    public function test_subject_name_must_be_globally_unique(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();

        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$classroom->id],
            'name' => 'Francais',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'A subject with this name already exists.',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'local_ids' => [$otherClassroom->id],
            'name' => 'Francais',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'A subject with this name already exists.',
            ])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_subject_can_keep_own_name_when_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), [
            'local_ids' => [$classroom->id, $otherClassroom->id],
            'name' => 'Francais',
            'description' => 'Updated description',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $subject->refresh();

        $this->assertSame('Francais', $subject->name);
        $this->assertSame('Updated description', $subject->description);
        $this->assertEqualsCanonicalizing(
            [$classroom->id, $otherClassroom->id],
            $subject->locals()->pluck('classrooms.id')->all(),
        );
    }

    public function test_subject_can_not_be_updated_to_another_subject_name(): void
    {
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create();
        Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Francais',
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Mathematique',
        ]);

        $this->actingAs($admin)->patch(route('admin.subjects.update', $subject), [
            'local_ids' => [$classroom->id],
            'name' => 'Francais',
            'is_active' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast', [
                'type' => 'error',
                'message' => 'A subject with this name already exists.',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Mathematique', $subject->refresh()->name);
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
        $this->assertDatabaseMissing('local_subject', ['subject_id' => $subject->id]);
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
        $this->assertDatabaseMissing('local_subject', ['local_id' => $classroom->id]);
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
