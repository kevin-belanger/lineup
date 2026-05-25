<?php

namespace App\Models;

use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'sort_order',
])]
class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory;
}
