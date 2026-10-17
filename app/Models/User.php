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
        'role', 'status', 'rejection_reason', 'course', 'year_level', 'section', 'password_change_required', 'password_change_expires_at',
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

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('users'));
        static::updated(fn () => CfoService::touch('users'));
        static::deleted(fn () => CfoService::touch('users'));
    }
}
