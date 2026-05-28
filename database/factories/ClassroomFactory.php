<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Local '.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'public_enabled' => false,
            'public_slug' => null,
        ];
    }
}
