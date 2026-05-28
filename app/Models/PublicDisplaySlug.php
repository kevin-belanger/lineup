<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['slug'])]
class PublicDisplaySlug extends Model
{
    public static function reserveUnique(): self
    {
        while (true) {
            try {
                return self::query()->create([
                    'slug' => Str::lower(Str::random(5)),
                ]);
            } catch (UniqueConstraintViolationException) {
                //
            }
        }
    }
}
