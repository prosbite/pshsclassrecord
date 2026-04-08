<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'section_name',
        'grade_level_id',
        'status',
    ];
    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
