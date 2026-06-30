<?php

namespace App\Models;

use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'student_id',
    'classroom_id',
    'subject_id',
    'assigned_teacher_id',
    'is_priority',
    'priority_requested_by_teacher_id',
    'moodle_tile_number',
    'table_number',
    'type',
    'request_type',
    'status',
    'comment',
    'assigned_at',
    'completed_at',
    'calculated_wait_time_minutes',
    'calculated_response_time_minutes',
    'cancelled_by',
    'cancel_reason',
])]
class SupportRequest extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_READY = 'ready';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CANCELLED_BY_STUDENT = 'student';

    public const CANCELLED_BY_TEACHER = 'teacher';

    public const CANCELLED_BY_SYSTEM = 'system';

    public const CANCEL_REASON_NO_LONGER_NEEDED = 'no_longer_needed';

    public const CANCEL_REASON_CHANGED_CLASSROOM = 'changed_classroom';

    public const CANCEL_REASON_TEACHER_CANCELLED = 'teacher_cancelled';

    public const CANCEL_REASON_END_OF_DAY = 'end_of_day';

    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    public static function statusLabels(): array
    {
        return [
            self::STATUS_WAITING => __('Waiting'),
            self::STATUS_ASSIGNED => __('Assigned'),
            self::STATUS_PAUSED => __('Paused'),
            self::STATUS_READY => __('Ready to review'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_ASSIGNED,
            self::STATUS_PAUSED,
            self::STATUS_READY,
        ];
    }

    public static function teacherActiveStatuses(): array
    {
        return [
            self::STATUS_ASSIGNED,
            self::STATUS_PAUSED,
            self::STATUS_READY,
        ];
    }

    public static function historyStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    protected function casts(): array
    {
        return [
            'is_priority' => 'boolean',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'calculated_wait_time_minutes' => 'integer',
            'calculated_response_time_minutes' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id')->withTrashed();
    }

    public function priorityRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_requested_by_teacher_id')->withTrashed();
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function personalNotes(): HasMany
    {
        return $this->hasMany(PersonalNote::class);
    }

    public function fieldAnswers(): HasMany
    {
        return $this->hasMany(SupportRequestFieldAnswer::class)
            ->orderBy('sort_order')
            ->orderBy('field_name');
    }

    public function typeLabel(): string
    {
        if (is_string($this->request_type) && trim($this->request_type) !== '') {
            return $this->request_type;
        }

        return '';
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function studentDisplayName(): string
    {
        return $this->student?->displayName() ?? 'N/A';
    }

    public function assignedTeacherDisplayName(): string
    {
        return $this->assignedTeacher?->displayName() ?? 'N/A';
    }

    public function priorityRequesterDisplayName(): string
    {
        return $this->priorityRequester?->displayName() ?? 'N/A';
    }

    public function subjectUrl(): ?string
    {
        $url = $this->subject?->url;

        if ($url === null || trim($url) === '') {
            return null;
        }

        $values = $this->fieldPlaceholderValues();

        if ($this->shouldShowTableNumber()) {
            $values['table'] = (string) $this->table_number;
        }

        return preg_replace_callback('/\[([^\]]+)\]/u', function (array $matches) use ($values): string {
            $key = SubjectRequestField::keyForName($matches[1]);

            if (! array_key_exists($key, $values)) {
                return $matches[0];
            }

            return rawurlencode($values[$key]);
        }, $url) ?? $url;
    }

    public function fieldAnswerSummary(): string
    {
        return $this->fieldAnswers
            ->filter(fn (SupportRequestFieldAnswer $answer): bool => trim((string) $answer->value) !== '')
            ->map(fn (SupportRequestFieldAnswer $answer): string => "{$answer->field_name} {$answer->value}")
            ->implode(' · ');
    }

    public function shouldShowTableNumber(): bool
    {
        return trim((string) $this->table_number) !== '' && ($this->classroom?->requires_table_number ?? true);
    }

    /**
     * @return array<string, string>
     */
    public function fieldPlaceholderValues(): array
    {
        return $this->fieldAnswers
            ->filter(fn (SupportRequestFieldAnswer $answer): bool => trim((string) $answer->value) !== '')
            ->mapWithKeys(fn (SupportRequestFieldAnswer $answer): array => [
                $answer->field_key => (string) $answer->value,
            ])
            ->all();
    }
}
