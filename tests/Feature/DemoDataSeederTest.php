<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_creates_rich_statistics_scenario(): void
    {
        $this->seed(DemoDataSeeder::class);

        $oldStudentId = User::query()->where('email', 'student01@example.com')->value('id');
        $oldClassroomId = Classroom::query()->where('name', 'Room 101')->value('id');
        $oldSubjectId = Subject::query()->where('name', 'Mathematiques 101')->value('id');
        $oldTeacherId = User::query()->where('email', 'peter@example.com')->value('id');

        SupportRequest::factory()
            ->for(User::query()->where('email', 'student02@example.com')->firstOrFail(), 'student')
            ->create([
                'classroom_id' => $oldClassroomId,
                'subject_id' => $oldSubjectId,
                'assigned_teacher_id' => $oldTeacherId,
                'status' => SupportRequest::STATUS_CANCELLED,
            ]);

        User::query()
            ->where('email', 'student01@example.com')
            ->update(['first_name' => 'Stale']);

        $this->seed(DemoDataSeeder::class);

        $classrooms = Classroom::query()
            ->whereIn('name', ['Room 101', 'Room 102', 'Room 103'])
            ->orderBy('name')
            ->get();
        $teachers = User::query()
            ->whereIn('email', ['peter@example.com', 'nancy@example.com', 'jack@example.com'])
            ->orderBy('email')
            ->get();
        $students = User::query()
            ->where('email', 'like', 'student%@example.com')
            ->get();

        $this->assertCount(3, $classrooms);
        $this->assertCount(3, $teachers);
        $this->assertCount(50, $students);
        $this->assertSame(12, Subject::query()->where('name', 'like', '% 10%')->count());
        $this->assertDatabaseMissing('users', ['id' => $oldStudentId]);
        $this->assertDatabaseMissing('classrooms', ['id' => $oldClassroomId]);
        $this->assertDatabaseMissing('subjects', ['id' => $oldSubjectId]);
        $this->assertDatabaseMissing('users', ['first_name' => 'Stale']);

        foreach ($classrooms as $classroom) {
            $this->assertSame(4, $classroom->subjects()->count());
        }

        $this->assertLessThanOrEqual(50 * 30 * 15, SupportRequest::query()->count());
        $this->assertSame(
            SupportRequest::query()->count(),
            SupportRequest::query()->where('status', SupportRequest::STATUS_COMPLETED)->count(),
        );

        $requestsByStudentDay = SupportRequest::query()
            ->selectRaw('student_id, DATE(created_at) as request_date, COUNT(*) as request_count')
            ->groupBy('student_id', 'request_date')
            ->get();

        $this->assertLessThanOrEqual(50 * 30, $requestsByStudentDay->count());

        foreach ($requestsByStudentDay as $row) {
            $this->assertGreaterThanOrEqual(1, $row->request_count);
            $this->assertLessThanOrEqual(15, $row->request_count);
        }

        $requestsByClassroomDay = SupportRequest::query()
            ->selectRaw('classroom_id, DATE(created_at) as request_date, COUNT(*) as request_count')
            ->groupBy('classroom_id', 'request_date')
            ->get();

        $this->assertLessThanOrEqual(3 * 30, $requestsByClassroomDay->count());

        foreach ($classrooms->values() as $index => $classroom) {
            $teacher = match ($index) {
                0 => User::query()->where('email', 'peter@example.com')->firstOrFail(),
                1 => User::query()->where('email', 'nancy@example.com')->firstOrFail(),
                default => User::query()->where('email', 'jack@example.com')->firstOrFail(),
            };

            $this->assertSame(
                0,
                SupportRequest::query()
                    ->where('classroom_id', $classroom->id)
                    ->where('assigned_teacher_id', '!=', $teacher->id)
                    ->count(),
            );
        }
    }
}
