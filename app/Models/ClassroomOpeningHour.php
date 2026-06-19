<?php

namespace App\Models;

use Database\Factories\ClassroomOpeningHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'classroom_id',
    'days',
    'opens_at',
    'closes_at',
    'sort_order',
])]
class ClassroomOpeningHour extends Model
{
    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /** @use HasFactory<ClassroomOpeningHourFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'days' => 'array',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
