<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $classroom = Classroom::factory()->create();

        return [
            'student_id' => User::factory(),
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->for($classroom),
            'assigned_teacher_id' => null,
            'moodle_tile_number' => fake()->numberBetween(1, 200),
            'table_number' => (string) fake()->numberBetween(1, 40),
            'type' => fake()->randomElement(array_keys(SupportRequest::typeLabels())),
            'status' => SupportRequest::STATUS_WAITING,
            'comment' => fake()->optional()->sentence(),
            'assigned_at' => null,
            'completed_at' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_teacher_id' => User::factory()->teacher(),
            'status' => SupportRequest::STATUS_PAUSED,
            'assigned_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_teacher_id' => User::factory()->teacher(),
            'status' => SupportRequest::STATUS_COMPLETED,
            'assigned_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }
}
