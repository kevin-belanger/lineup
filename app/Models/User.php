<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
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
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
