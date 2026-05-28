<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicClassroomDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_new_classroom_public_display_is_disabled_by_default(): void
    {
        $classroom = Classroom::factory()->create();

        $this->assertFalse($classroom->public_enabled);
        $this->assertNull($classroom->public_slug);
    }

    public function test_public_display_requires_enabled_classroom_with_active_slug(): void
    {
        $disabled = Classroom::factory()->create([
            'public_enabled' => false,
            'public_slug' => 'ab123',
        ]);
        $enabled = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'cd456',
        ]);

        $this->get(route('public-display.show', $disabled->public_slug))->assertNotFound();

        $this->get(route('public-display.show', $enabled->public_slug))
            ->assertOk()
            ->assertSee($enabled->name);

        $enabled->update([
            'public_enabled' => false,
            'public_slug' => null,
        ]);

        $this->get('/display/cd456')->assertNotFound();
    }

    public function test_public_display_shows_only_waiting_requests_for_the_classroom_oldest_first(): void
    {
        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'qw123',
        ]);
        $otherClassroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'er456',
        ]);
        $oldestStudent = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Alpha']);
        $newestStudent = User::factory()->create(['first_name' => 'Zoé', 'last_name' => 'Zulu']);
        $assignedStudent = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Busy']);
        $otherStudent = User::factory()->create(['first_name' => 'Clara', 'last_name' => 'Elsewhere']);

        $newest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'student_id' => $newestStudent->id,
            'status' => SupportRequest::STATUS_WAITING,
            'table_number' => '12',
            'created_at' => now()->subMinutes(5),
        ]);
        $oldest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'student_id' => $oldestStudent->id,
            'status' => SupportRequest::STATUS_WAITING,
            'table_number' => '3',
            'created_at' => now()->subMinutes(10),
        ]);
        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'student_id' => $assignedStudent->id,
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_teacher_id' => User::factory()->teacher(),
        ]);
        SupportRequest::factory()->create([
            'classroom_id' => $otherClassroom->id,
            'student_id' => $otherStudent->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $response = $this->get(route('public-display.show', $classroom->public_slug));

        $response
            ->assertOk()
            ->assertSeeInOrder([$oldest->student->fullName(), $newest->student->fullName()])
            ->assertSee('3')
            ->assertSee('12')
            ->assertDontSee($assignedStudent->fullName())
            ->assertDontSee($otherStudent->fullName())
            ->assertDontSee('Take')
            ->assertDontSee('Tile');
    }

    public function test_public_requests_endpoint_uses_version_to_skip_unchanged_lists(): void
    {
        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'rt789',
        ]);
        SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'student_id' => User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Ready'])->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $changeMarker = app(SupportRequestChangeMarker::class);
        $this->assertSame(0, $changeMarker->current($classroom));

        $this->getJson(route('public-display.requests', [
            'slug' => $classroom->public_slug,
            'version' => 0,
        ]))->assertNoContent();

        $changeMarker->touch($classroom);

        $this->getJson(route('public-display.requests', [
            'slug' => $classroom->public_slug,
            'version' => 0,
        ]))
            ->assertOk()
            ->assertJsonPath('version', 1)
            ->assertSee('Nadia Ready');
    }

    public function test_request_disappears_from_public_display_after_it_is_taken(): void
    {
        $classroom = Classroom::factory()->create([
            'public_enabled' => true,
            'public_slug' => 'uv234',
        ]);
        $supportRequest = SupportRequest::factory()->create([
            'classroom_id' => $classroom->id,
            'student_id' => User::factory()->create(['first_name' => 'Mila', 'last_name' => 'Wait'])->id,
            'status' => SupportRequest::STATUS_WAITING,
        ]);

        $supportRequest->update([
            'status' => SupportRequest::STATUS_ASSIGNED,
            'assigned_teacher_id' => User::factory()->teacher()->create()->id,
            'assigned_at' => now(),
        ]);
        app(SupportRequestChangeMarker::class)->touch($classroom);

        $this->getJson(route('public-display.requests', [
            'slug' => $classroom->public_slug,
            'version' => 0,
        ]))
            ->assertOk()
            ->assertSee('No waiting requests.')
            ->assertDontSee('Mila Wait');
    }
}
