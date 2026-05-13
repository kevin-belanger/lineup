<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Subject $subject): void {
            if ($subject->classroom_id !== null) {
                $subject->locals()->syncWithoutDetaching([$subject->classroom_id]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'url' => null,
            'is_active' => true,
        ];
    }
}
