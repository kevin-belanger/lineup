<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectRequestField;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (SupportRequest $supportRequest): void {
            if ($supportRequest->subject_id === null || $supportRequest->moodle_tile_number === null) {
                return;
            }

            $field = SubjectRequestField::query()->firstOrCreate(
                [
                    'subject_id' => $supportRequest->subject_id,
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

            $supportRequest->fieldAnswers()->firstOrCreate(
                [
                    'subject_request_field_id' => $field->id,
                ],
                [
                    'field_name' => $field->name,
                    'field_key' => $field->key,
                    'field_type' => $field->type,
                    'value' => (string) $supportRequest->moodle_tile_number,
                    'sort_order' => $field->sort_order,
                ],
            );
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $classroom = Classroom::factory()->create();

        $requestType = fake()->randomElement(['Explanation', 'Validation', 'Correction']);

        return [
            'student_id' => User::factory(),
            'classroom_id' => $classroom->id,
            'subject_id' => Subject::factory()->for($classroom),
            'assigned_teacher_id' => null,
            'is_priority' => false,
            'priority_requested_by_teacher_id' => null,
            'moodle_tile_number' => fake()->numberBetween(1, 200),
            'table_number' => (string) fake()->numberBetween(1, 40),
            'type' => strtolower($requestType),
            'request_type' => $requestType,
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
