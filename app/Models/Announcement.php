<?php

namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'cfo_announcements';
    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime'];
    protected $appends = ['draft_revision'];

    public function getDraftRevisionAttribute(): ?string
    {
        if ($this->published_at !== null) return null;

        return hash('sha256', json_encode([
            $this->title, $this->preview, $this->body, (string) $this->getRawOriginal('updated_at'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('announcements'));
        static::updated(fn () => CfoService::touch('announcements'));
        static::deleted(fn () => CfoService::touch('announcements'));
    }
}
