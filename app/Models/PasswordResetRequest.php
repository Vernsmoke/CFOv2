<?php
namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class PasswordResetRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['recovery_hash', 'temp_password_hash'];
    protected $casts = ['expires_at' => 'datetime', 'reviewed_at' => 'datetime', 'verified_at' => 'datetime', 'used_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('password_resets'));
        static::updated(fn () => CfoService::touch('password_resets'));
        static::deleted(fn () => CfoService::touch('password_resets'));
    }
}
