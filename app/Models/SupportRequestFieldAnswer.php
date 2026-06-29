<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'support_request_id',
    'subject_request_field_id',
    'field_name',
    'field_key',
    'field_type',
    'value',
    'sort_order',
])]
class SupportRequestFieldAnswer extends Model
{
    use HasFactory;

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(SubjectRequestField::class, 'subject_request_field_id');
    }
}
