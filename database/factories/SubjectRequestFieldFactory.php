<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\SubjectRequestField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectRequestField>
 */
class SubjectRequestFieldFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'subject_id' => Subject::factory(),
            'name' => $name,
            'key' => SubjectRequestField::keyForName($name),
            'type' => SubjectRequestField::TYPE_TEXT,
            'is_required' => false,
            'sort_order' => 0,
            'archived_at' => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => true,
        ]);
    }

    public function integer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SubjectRequestField::TYPE_INTEGER,
        ]);
    }

    public function decimal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => SubjectRequestField::TYPE_DECIMAL,
        ]);
    }
}
