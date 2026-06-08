<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'password',
    'is_student',
    'is_teacher',
    'is_admin',
    'is_approved',
    'approved_at',
    'approved_by',
    'is_active',
    'preferred_locale',
    'place_new_requests_on_top',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_student' => 'boolean',
            'is_teacher' => 'boolean',
            'is_admin' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
            'place_new_requests_on_top' => 'boolean',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function approvedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'approved_by');
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'student_id');
    }

    public function assignedSupportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class, 'assigned_teacher_id');
    }

    public function hasRole(string $role): bool
    {
        return match ($role) {
            'student' => $this->is_student,
            'teacher' => $this->is_teacher,
            'admin' => $this->is_admin,
            default => false,
        };
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ], fn ($value): bool => $value !== null && $value !== '')));
    }

    public function displayName(): string
    {
        $name = $this->fullName();

        if ($this->trashed()) {
            return __(':name (deleted user)', ['name' => $name]);
        }

        return $name;
    }

    public function canManageAdministration(): bool
    {
        return $this->is_admin || $this->is_teacher;
    }

    public function canManageSettings(): bool
    {
        return $this->is_admin;
    }

    public function homeRouteName(): string
    {
        if ($this->is_teacher) {
            return 'teacher.dashboard';
        }

        if ($this->is_admin) {
            return 'admin.dashboard';
        }

        return 'student.dashboard';
    }
}
