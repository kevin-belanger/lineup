<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'name' => 'Joe',
                'email' => 'joe@example.com',
                'is_student' => true,
                'is_teacher' => false,
                'is_admin' => false,
            ],
            [
                'name' => 'Pierre',
                'email' => 'pierre@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'name' => 'Jean',
                'email' => 'jean@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'name' => 'Jacques',
                'email' => 'jacques@example.com',
                'is_student' => false,
                'is_teacher' => true,
                'is_admin' => false,
            ],
            [
                'name' => 'Admin',
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
                    'name' => $demoUser['name'],
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
            ['name' => 'Local 101', 'description' => 'Classe principale'],
            ['name' => 'Local 102', 'description' => 'Classe de support'],
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

        foreach ($createdClassrooms as $classroom) {
            foreach ($subjects as $subjectName) {
                Subject::query()->firstOrCreate(
                    ['classroom_id' => $classroom->id, 'name' => $subjectName],
                    ['description' => null, 'is_active' => true],
                );
            }
        }
    }
}
