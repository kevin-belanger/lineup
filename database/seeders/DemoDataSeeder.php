<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demonstration users, classrooms, and subjects.
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'first_name' => 'Bob',
                'last_name' => 'Student',
                'email' => 'bob@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Alice',
                'last_name' => 'Student',
                'email' => 'alice@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Joe',
                'last_name' => 'Student',
                'email' => 'joe@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Peter',
                'last_name' => 'Teacher',
                'email' => 'peter@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Nancy',
                'last_name' => 'Teacher',
                'email' => 'nancy@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Jack',
                'last_name' => 'Teacher',
                'email' => 'jack@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'first_name' => 'Admin',
                'last_name' => 'Demo',
                'email' => 'admin@example.com',
                'is_student' => false,
                'is_teacher' => false,
                'is_admin' => true,
            ],
        ];

        $admin = null;

        foreach ($demoUsers as $demoUser) {
            $user = User::query()->updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'first_name' => $demoUser['first_name'],
                    'last_name' => $demoUser['last_name'],
                    'password' => Hash::make('password'),
                    'is_student' => $demoUser['is_student'],
                    'is_teacher' => $demoUser['is_teacher'],
                    'is_admin' => $demoUser['is_admin'],
                    'is_approved' => true,
                    'approved_at' => now(),
                    'is_active' => true,
                ],
            );

            if ($demoUser['is_admin']) {
                $admin = $user;
            }
        }

        if ($admin !== null) {
            User::query()
                ->whereIn('email', collect($demoUsers)->pluck('email'))
                ->whereNull('approved_by')
                ->update(['approved_by' => $admin->id]);
        }

        $classrooms = [
            ['name' => 'Room 101', 'description' => 'Main room'],
            ['name' => 'Room 102', 'description' => 'Support room'],
        ];

        $createdClassrooms = collect();

        foreach ($classrooms as $classroom) {
            $createdClassrooms->push(
                Classroom::query()->firstOrCreate(
                    ['name' => $classroom['name']],
                    ['description' => $classroom['description'], 'is_active' => true],
                )
            );
        }

        $subjects = ['Mathematiques', 'Francais'];

        foreach ($subjects as $subjectName) {
            $subject = Subject::query()->firstOrCreate(
                ['name' => $subjectName],
                [
                    'classroom_id' => $createdClassrooms->first()?->id,
                    'description' => null,
                    'is_active' => true,
                ],
            );

            $subject->locals()->syncWithoutDetaching($createdClassrooms->pluck('id')->all());
        }
    }
}
