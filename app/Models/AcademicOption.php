<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicOption extends Model
{
    protected $table = 'academic_options';
    protected $guarded = ['id'];

    public function schedules()
    {
        return $this->belongsToMany(Schedule::class, 'recollection_schedule_courses', 'academic_option_id', 'schedule_id')->withTimestamps();
    }
}
