<?php
namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class ScheduleChange extends Model
{
    protected $table = 'schedule_change_requests';
    protected $guarded = ['id'];
    protected $attributes = ['status' => 'Pending'];
    protected $appends = ['student_status', 'student_message'];
    public function getStudentStatusAttribute(): string
    {
        return in_array($this->status, ['Rejected', 'Closed'], true) ? 'Not Approved' : $this->status;
    }
    public function getStudentMessageAttribute(): string
    {
        return match ($this->status) {
            'Rejected' => CfoService::scheduleChangeRejectionMessage($this->review_reason),
            'Closed' => 'This request was closed because your schedule changed. Please visit the CFO office, Miraculous Building, near the chapel, for follow-up.',
            'Cancelled' => 'You cancelled this request.',
            'Approved' => $this->assignedSchedule
                ? 'Your schedule change request was approved. You are now assigned to '.$this->assignedSchedule->title.' on '.$this->assignedSchedule->date.'.'
                : 'Your schedule change request was approved.',
            default => 'Your schedule change request is pending CFO approval. The CFO will assign your new schedule if approved. Your current schedule remains assigned until then.',
        };
    }
    public function registration() { return $this->belongsTo(Registration::class); }
    public function requestedSchedule() { return $this->belongsTo(Schedule::class, 'requested_schedule_id'); }
    public function assignedSchedule() { return $this->belongsTo(Schedule::class, 'assigned_schedule_id'); }
    public function currentSchedule() { return $this->belongsTo(Schedule::class, 'current_schedule_id'); }

    protected static function booted(): void
    {
        static::created(fn () => CfoService::touch('schedule_changes'));
        static::updated(fn () => CfoService::touch('schedule_changes'));
        static::deleted(fn () => CfoService::touch('schedule_changes'));
    }
}
