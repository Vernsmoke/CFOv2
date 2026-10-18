<?php

namespace App\Models;

use App\Services\CfoService;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_STUDENT = 'Student';

    public const ROLE_CFO_STAFF = 'CFOStaff';

    public const ROLE_SUPER_ADMIN = 'SuperAdmin';

    public const ROLE_LEGACY_ADMIN = 'Admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'student_id', 'system_username', 'original_email', 'login_email', 'first_name', 'middle_name', 'last_name',
        'role', 'status', 'rejection_reason', 'department', 'course', 'major', 'year_level', 'section', 'password_change_required', 'password_change_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'password_change_required' => 'boolean',
            'password_change_expires_at' => 'datetime',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isCfoStaff(): bool
    {
        return $this->role === self::ROLE_CFO_STAFF;
    }

    public function isSuperAdmin(): bool
    {
        // Legacy Admin is recognized during rolling deployments. The role
        // migration converts every persisted Admin account to SuperAdmin.
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_LEGACY_ADMIN], true);
    }

    public function hasOfficeAccess(): bool
    {
        return $this->isCfoStaff() || $this->isSuperAdmin();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_CFO_STAFF => 'CFO Staff',
            self::ROLE_SUPER_ADMIN, self::ROLE_LEGACY_ADMIN => 'Super Admin',
            default => 'Student',
        };
    }

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('users'));
        static::updated(fn () => CfoService::touch('users'));
        static::deleted(fn () => CfoService::touch('users'));
    }
}
