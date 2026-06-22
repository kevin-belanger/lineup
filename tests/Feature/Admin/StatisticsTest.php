<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RequestStatistics;
use App\Livewire\Admin\RequestTileStatistics;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_statistics_page_is_available_to_teachers_and_admins(): void
    {
        $teacher = User::factory()->teacher()->create();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->actingAs($teacher)
            ->get(route('admin.statistics.index'))
            ->assertOk()
            ->assertSee('Request statistics');

        $this->actingAs($admin)
            ->get(route('admin.statistics.index'))
            ->assertOk()
            ->assertSee('Request statistics');

        $this->actingAs($student)
            ->get(route('admin.statistics.index'))
            ->assertForbidden();
    }

    public function test_statistics_use_completed_requests_from_the_last_30_days_by_default(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');

        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $classroom = Classroom::factory()->create(['name' => 'Local 101']);

        SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => Carbon::parse('2026-06-22 09:00:00'),
            'calculated_wait_time_minutes' => 10,
            'calculated_response_time_minutes' => 20,
        ]);
        SupportRequest::factory()->completed()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_COMPLETED,
            'completed_at' => Carbon::parse('2026-06-22 10:00:00'),
            'calculated_wait_time_minutes' => 30,
            'calculated_response_time_minutes' => null,
        ]);
        SupportRequest::factory()->create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'status' => SupportRequest::STATUS_CANCELLED,
            'completed_at' => Carbon::parse('2026-06-22 11:00:00'),
            'calculated_wait_time_minutes' => 999,
            'calculated_response_time_minutes' => 999,
        ]);
        SupportRequest::factory()->completed()->create([
            'student_id' => User::factory()->create()->id,
            'classroom_id' => $classroom->id,
            'completed_at' => Carbon::parse('2026-06-21 10:00:00'),
            'calculated_wait_time_minutes' => 50,
            'calculated_response_time_minutes' => 60,
        ]);
        SupportRequest::factory()->completed()->create([
            'student_id' => User::factory()->create()->id,
            'classroom_id' => $classroom->id,
            'completed_at' => Carbon::parse('2026-05-23 10:00:00'),
            'calculated_wait_time_minutes' => 70,
            'calculated_response_time_minutes' => 80,
        ]);

        Livewire::actingAs($teacher)
            ->test(RequestStatistics::class)
            ->assertSee('Completed requests')
            ->assertSee('Last 30 days')
            ->assertDontSee('Year')
            ->assertSee('2026-05-24')
            ->assertSee('2026-06-22')
            ->assertSee('>3</div>', false)
            ->assertSee('Distinct students')
            ->assertSee('30 min')
            ->assertSee('10 min')
            ->assertSee('50 min');
    }

    public function test_statistics_can_filter_by_multiple_rooms(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');

        $teacher = User::factory()->teacher()->create();
        $firstClassroom = Classroom::factory()->create(['name' => 'Local 101']);
        $secondClassroom = Classroom::factory()->create(['name' => 'Local 202']);

        SupportRequest::factory()->completed()->create([
            'classroom_id' => $firstClassroom->id,
            'completed_at' => Carbon::parse('2026-06-22 09:00:00'),
            'calculated_wait_time_minutes' => 10,
            'calculated_response_time_minutes' => 20,
        ]);
        SupportRequest::factory()->completed()->create([
            'classroom_id' => $secondClassroom->id,
            'completed_at' => Carbon::parse('2026-06-22 10:00:00'),
            'calculated_wait_time_minutes' => 30,
            'calculated_response_time_minutes' => 40,
        ]);

        Livewire::actingAs($teacher)
            ->test(RequestStatistics::class)
            ->set('classroomIds', [(string) $secondClassroom->id])
            ->assertSee('30 min')
            ->assertSee('40 min')
            ->assertDontSee('10 min');
    }

    public function test_statistics_show_subject_and_tile_breakdowns(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');

        $teacher = User::factory()->teacher()->create();
        $classroom = Classroom::factory()->create();
        $math = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Mathematiques',
        ]);
        $science = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'name' => 'Sciences',
        ]);

        SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $math->id,
            'moodle_tile_number' => 3,
            'completed_at' => Carbon::parse('2026-06-22 09:00:00'),
            'calculated_wait_time_minutes' => 10,
            'calculated_response_time_minutes' => 20,
        ]);
        SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $math->id,
            'moodle_tile_number' => 3,
            'completed_at' => Carbon::parse('2026-06-22 10:00:00'),
            'calculated_wait_time_minutes' => 30,
            'calculated_response_time_minutes' => 40,
        ]);
        SupportRequest::factory()->completed()->create([
            'classroom_id' => $classroom->id,
            'subject_id' => $science->id,
            'moodle_tile_number' => 8,
            'completed_at' => Carbon::parse('2026-06-22 11:00:00'),
            'calculated_wait_time_minutes' => 50,
            'calculated_response_time_minutes' => 60,
        ]);

        Livewire::actingAs($teacher)
            ->test(RequestStatistics::class)
            ->assertSee('Requests by subject')
            ->assertSee('Mathematiques')
            ->assertSee('Sciences')
            ->assertSee('Requests by Moodle tile');

        Livewire::actingAs($teacher)
            ->test(RequestTileStatistics::class)
            ->set('selectedSubjectId', (string) $math->id)
            ->assertSee('Subject: Mathematiques')
            ->assertSee('>3</td>', false)
            ->assertSee('20 min')
            ->assertSee('30 min')
            ->assertDontSee('>8</td>', false);
    }
}
