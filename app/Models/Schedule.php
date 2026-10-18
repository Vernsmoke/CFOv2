<?php
namespace App\Models;

use App\Services\CfoService;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'recollection_schedules';
    protected $guarded = ['id'];
    protected $hidden = ['theme', 'facilitator', 'description'];
    protected $attributes = ['active' => true];
    private bool $statusExplicitlyAssigned = false;
    protected $casts = ['active' => 'boolean', 'capacity' => 'integer', 'slot_limit' => 'integer', 'year_levels' => 'array', 'sections' => 'array', 'course_majors' => 'array'];
    protected $appends = ['time', 'year', 'display_status', 'course_sections', 'course_sections_by_year'];
    public function getTimeAttribute(): string { return $this->start_time.' – '.$this->end_time; }
    public function getYearAttribute(): int { return (int) substr($this->date, 0, 4); }
    public function getYearLevelsAttribute($value): array { return $this->academicSelections($value, $this->year_level); }
    public function getSectionsAttribute($value): array { return $this->academicSelections($value, $this->section); }
    public function getDisplayStatusAttribute(): string { return $this->finalized_at ? 'Completed' : ($this->status ?: ($this->active ? 'Open' : 'Closed')); }
    public function setStatusAttribute($value): void
    {
        $this->attributes['status'] = $value;
        $this->statusExplicitlyAssigned = true;
    }
    public function scopeOpen($query) { return $query->where('status', 'Open')->where('active', true)->whereNull('finalized_at'); }
    public function isOpen(): bool { return $this->status === 'Open' && $this->active && ! $this->finalized_at; }

    public function scopeWithScheduleLinks($query)
    {
        return $query->with('courses')->withCount([
            'registrations' => fn ($registrations) => $registrations->active(),
            'registrations as registrations_total_count',
            'registrations as registrations_recorded_attendance_count' => fn ($registrations) => $registrations->where('attendance', '!=', 'Not Yet Recorded'),
            'currentChangeRequests', 'requestedChangeRequests', 'assignedChangeRequests',
            'currentChangeRequests as current_pending_change_requests_count' => fn ($requests) => $requests->where('status', 'Pending'),
            'requestedChangeRequests as requested_pending_change_requests_count' => fn ($requests) => $requests->where('status', 'Pending'),
        ]);
    }

    public function getHasLinkedRecordsAttribute(): bool
    {
        if (array_key_exists('registrations_total_count', $this->attributes)) {
            $assigned = array_key_exists('assigned_change_requests_count', $this->attributes)
                ? $this->assigned_change_requests_count > 0 : $this->assignedChangeRequests()->exists();
            return $this->registrations_total_count > 0 || $this->current_change_requests_count > 0 || $this->requested_change_requests_count > 0 || $assigned;
        }
        return $this->registrations()->exists() || $this->currentChangeRequests()->exists() || $this->requestedChangeRequests()->exists() || $this->assignedChangeRequests()->exists();
    }

    private function academicSelections($value, ?string $legacy): array
    {
        if ($value === null) return $legacy === null || $legacy === '' ? [] : [$legacy];
        $values = is_string($value) ? json_decode($value, true) : $value;
        return is_array($values) ? array_values(array_unique(array_map('strval', $values))) : [];
    }

    public function includesYearLevel(?string $value): bool
    {
        return $this->year_levels === []
            ? ($this->getAttributes()['year_levels'] ?? null) === null && ! $this->year_level
            : in_array($value, $this->year_levels, true);
    }
    public function includesSection(?string $value): bool
    {
        return $this->sections === []
            ? ($this->getAttributes()['sections'] ?? null) === null && ! $this->section
            : in_array($value, $this->sections, true);
    }
    public function getCourseSectionsAttribute(): object
    {
        $sections = [];
        foreach ($this->courses as $course) {
            if ($course->pivot->sections !== null) $sections[(string) $course->id] = $this->sectionsForCourse($course->id);
        }
        return (object) $sections;
    }

    public function getCourseSectionsByYearAttribute(): object
    {
        $sections = [];
        foreach ($this->courses as $course) {
            if ($this->hasYearSpecificSectionsForCourse($course->id)) $sections[(string) $course->id] = (object) $course->pivot->sections;
        }
        return (object) $sections;
    }

    public function hasYearSpecificSectionsForCourse(int $courseId): bool
    {
        $sections = $this->courses->firstWhere('id', $courseId)?->pivot->sections;
        return is_array($sections) && $sections !== [] && is_array(reset($sections));
    }

    public function sectionsForCourse(int $courseId, ?string $yearLevel = null): array
    {
        $course = $this->courses->firstWhere('id', $courseId);
        if ($this->hasYearSpecificSectionsForCourse($courseId)) {
            if ($yearLevel !== null) return $course->pivot->sections[$yearLevel] ?? [];
            return array_values(array_unique(array_merge(...array_values($course->pivot->sections))));
        }
        return $course?->pivot->sections ?? $this->sections;
    }

    public function hasCourseSections(): bool { return (array) $this->course_sections !== []; }

    public function includesSectionForCourse(?string $courseName, ?string $section, ?string $yearLevel = null): bool
    {
        $course = $this->courses->firstWhere('name', $courseName);
        if ($course && $course->pivot->sections !== null) return in_array($section, $this->sectionsForCourse($course->id, $yearLevel), true);
        return $this->includesSection($section);
    }

    public function majorForCourse(int|string $courseId): ?string
    {
        $major = trim((string) (($this->course_majors ?? [])[(string) $courseId] ?? ''));

        return $major === '' ? null : $major;
    }

    public function includesMajorForCourse(?string $courseName, ?string $major): bool
    {
        $course = $this->courses->first(fn ($item) => strcasecmp(trim($item->name), trim((string) $courseName)) === 0);
        $required = $course ? $this->majorForCourse($course->id) : null;

        return $required === null || strcasecmp($required, trim((string) $major)) === 0;
    }

    public function allowsAcademicGroup(User $student): bool
    {
        if ($this->hasCourseSections() && ! $this->courses->contains('name', $student->course)) return false;
        return $this->includesMajorForCourse($student->course, $student->major)
            && $this->includesYearLevel($student->year_level)
            && $this->includesSectionForCourse($student->course, $student->section, $student->year_level);
    }
    protected static function booted(): void
    {
        static::saving(function (self $schedule) {
            // Legacy code still writes active (including batch finalization).
            // New callers write status; keep both representations consistent.
            if (! $schedule->exists && ! $schedule->statusExplicitlyAssigned) {
                $schedule->status = $schedule->active ? 'Open' : 'Closed';
            } elseif (! $schedule->statusExplicitlyAssigned && $schedule->isDirty('active') && $schedule->status !== 'Cancelled') {
                $schedule->status = $schedule->active ? 'Open' : 'Closed';
            }
            $schedule->active = $schedule->status === 'Open' && ! $schedule->finalized_at;
        });
        static::saved(fn (self $schedule) => $schedule->statusExplicitlyAssigned = false);
        static::created(fn () => CfoService::touch('schedules'));
        static::updated(fn () => CfoService::touch('schedules'));
        static::deleted(fn () => CfoService::touch('schedules'));
    }

    public function registrations() { return $this->hasMany(Registration::class, 'recollection_schedule_id'); }
    public function currentChangeRequests() { return $this->hasMany(ScheduleChange::class, 'current_schedule_id'); }
    public function requestedChangeRequests() { return $this->hasMany(ScheduleChange::class, 'requested_schedule_id'); }
    public function assignedChangeRequests() { return $this->hasMany(ScheduleChange::class, 'assigned_schedule_id'); }
    public function courses() { return $this->belongsToMany(AcademicOption::class, 'recollection_schedule_courses', 'schedule_id', 'academic_option_id')->using(RecollectionScheduleCourse::class)->withPivot('sections')->withTimestamps(); }
}
