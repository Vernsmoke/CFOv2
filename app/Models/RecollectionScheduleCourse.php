<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RecollectionScheduleCourse extends Pivot
{
    protected $table = 'recollection_schedule_courses';
    public $incrementing = true;
    protected $casts = ['sections' => 'array'];
}
