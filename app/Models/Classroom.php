<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active', 'public_enabled', 'public_slug'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'public_enabled' => 'boolean',
        ];
    }

    public static function generateUniquePublicSlug(?self $ignoredClassroom = null): string
    {
        return PublicDisplaySlug::reserveUnique()->slug;
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'local_subject', 'local_id', 'subject_id')
            ->withTimestamps();
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(ClassroomOpeningHour::class)
            ->orderBy('sort_order')
            ->orderBy('opens_at');
    }
}
