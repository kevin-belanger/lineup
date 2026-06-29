<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectRequestField;
use App\Models\SupportRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private const DEMO_DAYS = 30;

    private const DEMO_STUDENT_COUNT = 50;

    private const ADMIN_EMAIL = 'admin@example.com';

    private const TEACHERS = [
        ['Peter', 'Teacher', 'peter@example.com'],
        ['Nancy', 'Teacher', 'nancy@example.com'],
        ['Jack', 'Teacher', 'jack@example.com'],
    ];

    private const CLASSROOMS = [
        ['Room 101', 'Main demonstration room'],
        ['Room 102', 'Second demonstration room'],
        ['Room 103', 'Third demonstration room'],
    ];

    private const SUBJECT_NAMES = [
        ['Mathematiques 101', 'Francais 101', 'Sciences 101', 'Anglais 101'],
        ['Mathematiques 102', 'Francais 102', 'Sciences 102', 'Histoire 102'],
        ['Mathematiques 103', 'Francais 103', 'Sciences 103', 'Geographie 103'],
    ];

    /**
     * Seed demonstration users, classrooms, and subjects.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->clearDemoData();

            $admin = $this->seedAdmin();
            $teachers = $this->seedTeachers($admin);
            $students = $this->seedStudents($admin);
            $classrooms = $this->seedClassrooms();
            $subjectsByClassroom = $this->seedSubjects($classrooms);

            $this->seedCompletedRequests($students, $teachers, $classrooms, $subjectsByClassroom);
        });
    }

    private function clearDemoData(): void
    {
        $userIds = User::withTrashed()
            ->whereIn('email', $this->demoUserEmails())
            ->pluck('id');
        $classroomIds = Classroom::query()
            ->whereIn('name', $this->demoClassroomNames())
            ->pluck('id');
        $subjectIds = Subject::query()
            ->whereIn('name', $this->demoSubjectNames())
            ->pluck('id');

        SupportRequest::query()
            ->where(function ($query) use ($userIds, $classroomIds, $subjectIds): void {
                $query
                    ->whereIn('student_id', $userIds)
                    ->orWhereIn('assigned_teacher_id', $userIds)
                    ->orWhereIn('priority_requested_by_teacher_id', $userIds)
                    ->orWhereIn('classroom_id', $classroomIds)
                    ->orWhereIn('subject_id', $subjectIds);
            })
            ->delete();

        Subject::query()
            ->whereIn('id', $subjectIds)
            ->delete();

        Classroom::query()
            ->whereIn('id', $classroomIds)
            ->delete();

        User::withTrashed()
            ->whereIn('id', $userIds)
            ->forceDelete();
    }

    private function seedAdmin(): User
    {
        return User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'first_name' => 'Admin',
                'last_name' => 'Demo',
                'password' => Hash::make('password'),
                'is_student' => false,
                'is_teacher' => false,
                'is_admin' => true,
                'is_approved' => true,
                'approved_at' => now(),
                'is_active' => true,
            ],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function seedTeachers(User $admin): Collection
    {
        return collect(self::TEACHERS)->map(fn (array $teacher): User => User::query()->updateOrCreate(
            ['email' => $teacher[2]],
            [
                'first_name' => $teacher[0],
                'last_name' => $teacher[1],
                'password' => Hash::make('password'),
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
                'is_approved' => true,
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'is_active' => true,
            ],
        ))->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedStudents(User $admin): Collection
    {
        return collect(range(1, self::DEMO_STUDENT_COUNT))
            ->map(fn (int $number): User => User::query()->updateOrCreate(
                ['email' => sprintf('student%02d@example.com', $number)],
                [
                    'first_name' => sprintf('Student%02d', $number),
                    'last_name' => 'Demo',
                    'password' => Hash::make('password'),
                    'is_student' => true,
                    'is_teacher' => false,
                    'is_admin' => false,
                    'is_approved' => true,
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                    'is_active' => true,
                ],
            ))
            ->values();
    }

    /**
     * @return Collection<int, Classroom>
     */
    private function seedClassrooms(): Collection
    {
        return collect(self::CLASSROOMS)->map(fn (array $classroom): Classroom => Classroom::query()->updateOrCreate(
            ['name' => $classroom[0]],
            [
                'description' => $classroom[1],
                'is_active' => true,
            ],
        ))->values();
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @return Collection<int, Collection<int, Subject>>
     */
    private function seedSubjects(Collection $classrooms): Collection
    {
        return $classrooms->map(function (Classroom $classroom, int $index): Collection {
            return collect(self::SUBJECT_NAMES[$index])->map(function (string $subjectName) use ($classroom): Subject {
                $subject = Subject::query()->updateOrCreate(
                    ['name' => $subjectName],
                    [
                        'classroom_id' => $classroom->id,
                        'description' => null,
                        'url' => 'https://moodle.example.test/course?table=[table]&section=[tuile moodle]',
                        'is_active' => true,
                    ],
                );

                $subject->locals()->sync([$classroom->id]);
                $this->ensureMoodleTileField($subject);

                return $subject;
            })->values();
        })->values();
    }

    private function ensureMoodleTileField(Subject $subject): SubjectRequestField
    {
        return SubjectRequestField::query()->updateOrCreate(
            [
                'subject_id' => $subject->id,
                'key' => SubjectRequestField::keyForName('Tuile Moodle'),
            ],
            [
                'name' => 'Tuile Moodle',
                'type' => SubjectRequestField::TYPE_INTEGER,
                'is_required' => true,
                'sort_order' => 0,
                'archived_at' => null,
            ],
        );
    }

    /**
     * @param  Collection<int, User>  $students
     * @param  Collection<int, User>  $teachers
     * @param  Collection<int, Classroom>  $classrooms
     * @param  Collection<int, Collection<int, Subject>>  $subjectsByClassroom
     */
    private function seedCompletedRequests(Collection $students, Collection $teachers, Collection $classrooms, Collection $subjectsByClassroom): void
    {
        $rows = [];
        $startDate = CarbonImmutable::now()->subDays(self::DEMO_DAYS - 1)->startOfDay();

        foreach (range(0, self::DEMO_DAYS - 1) as $dayOffset) {
            $day = $startDate->addDays($dayOffset);

            foreach ($students as $studentIndex => $student) {
                $classroomIndex = ($studentIndex + $dayOffset) % $classrooms->count();
                $classroom = $classrooms[$classroomIndex];
                $teacher = $teachers[$classroomIndex];
                $subjects = $subjectsByClassroom[$classroomIndex];
                $requestCount = random_int(0, 15);

                if ($requestCount === 0) {
                    continue;
                }

                foreach (range(1, $requestCount) as $requestNumber) {
                    $createdAt = $day
                        ->setTime(random_int(8, 15), random_int(0, 59), random_int(0, 59));
                    $waitMinutes = random_int(3, 75);
                    $interventionMinutes = random_int(5, 90);
                    $assignedAt = $createdAt->addMinutes($waitMinutes);
                    $completedAt = $assignedAt->addMinutes($interventionMinutes);
                    $subject = $subjects->random();
                    $requestType = collect(['Explanation', 'Validation', 'Correction'])->random();

                    $rows[] = [
                        'student_id' => $student->id,
                        'classroom_id' => $classroom->id,
                        'subject_id' => $subject->id,
                        'assigned_teacher_id' => $teacher->id,
                        'is_priority' => false,
                        'priority_requested_by_teacher_id' => null,
                        'moodle_tile_number' => random_int(1, 12),
                        'table_number' => (string) random_int(1, 40),
                        'type' => strtolower($requestType),
                        'request_type' => $requestType,
                        'status' => SupportRequest::STATUS_COMPLETED,
                        'comment' => $requestNumber % 7 === 0 ? 'Demo generated request' : null,
                        'assigned_at' => $assignedAt->toDateTimeString(),
                        'completed_at' => $completedAt->toDateTimeString(),
                        'calculated_wait_time_minutes' => $waitMinutes,
                        'calculated_response_time_minutes' => $interventionMinutes,
                        'cancelled_by' => null,
                        'cancel_reason' => null,
                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $completedAt->toDateTimeString(),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            SupportRequest::query()->insert($chunk);
        }

        $this->seedMoodleTileAnswers();
    }

    private function seedMoodleTileAnswers(): void
    {
        $fieldIds = SubjectRequestField::query()
            ->where('key', SubjectRequestField::keyForName('Tuile Moodle'))
            ->pluck('id', 'subject_id');

        SupportRequest::query()
            ->whereNotNull('subject_id')
            ->whereNotNull('moodle_tile_number')
            ->whereDoesntHave('fieldAnswers')
            ->orderBy('id')
            ->get(['id', 'subject_id', 'moodle_tile_number'])
            ->each(function (SupportRequest $supportRequest) use ($fieldIds): void {
                $fieldId = $fieldIds[$supportRequest->subject_id] ?? null;

                if ($fieldId === null) {
                    return;
                }

                $supportRequest->fieldAnswers()->create([
                    'subject_request_field_id' => $fieldId,
                    'field_name' => 'Tuile Moodle',
                    'field_key' => SubjectRequestField::keyForName('Tuile Moodle'),
                    'field_type' => SubjectRequestField::TYPE_INTEGER,
                    'value' => (string) $supportRequest->moodle_tile_number,
                    'sort_order' => 0,
                ]);
            });
    }

    /**
     * @return list<string>
     */
    private function demoUserEmails(): array
    {
        return [
            self::ADMIN_EMAIL,
            ...collect(self::TEACHERS)->pluck(2)->all(),
            ...collect(range(1, self::DEMO_STUDENT_COUNT))
                ->map(fn (int $number): string => sprintf('student%02d@example.com', $number))
                ->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function demoClassroomNames(): array
    {
        return collect(self::CLASSROOMS)->pluck(0)->all();
    }

    /**
     * @return list<string>
     */
    private function demoSubjectNames(): array
    {
        return collect(self::SUBJECT_NAMES)->flatten()->all();
    }
}
