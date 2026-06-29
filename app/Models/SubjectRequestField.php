<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'subject_id',
    'name',
    'key',
    'type',
    'is_required',
    'sort_order',
    'archived_at',
])]
class SubjectRequestField extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_DECIMAL = 'decimal';

    public static function types(): array
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_INTEGER,
            self::TYPE_DECIMAL,
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_TEXT => __('Text'),
            self::TYPE_INTEGER => __('Whole number'),
            self::TYPE_DECIMAL => __('Decimal number'),
        ];
    }

    public static function keyForName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SupportRequestFieldAnswer::class);
    }
}
