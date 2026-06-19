<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\ClassroomOpeningHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassroomOpeningHour>
 */
class ClassroomOpeningHourFactory extends Factory
{
    protected $model = ClassroomOpeningHour::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'days' => [1, 2, 3, 4, 5],
            'opens_at' => '08:00',
            'closes_at' => '16:00',
            'sort_order' => 0,
        ];
    }
}
