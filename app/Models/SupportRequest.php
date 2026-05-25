<?php

namespace App\Models;

use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_teacher_id');
    }

    public function priorityRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_requested_by_teacher_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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

    public function subjectUrl(): ?string
    {
        $url = $this->subject?->url;

        if ($url === null || trim($url) === '') {
            return null;
        }

        if ($this->table_number !== null) {
            $url = str_replace('[table]', (string) $this->table_number, $url);
        }

        if ($this->moodle_tile_number !== null) {
            $url = str_replace('[section]', (string) $this->moodle_tile_number, $url);
        }

        return $url;
    }
}
