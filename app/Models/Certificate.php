<?php
namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'revoked_at' => 'datetime'];
    public function registration() { return $this->belongsTo(Registration::class); }

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('registrations'));
        static::updated(fn () => CfoService::touch('registrations'));
        static::deleted(fn () => CfoService::touch('registrations'));
    }
}
