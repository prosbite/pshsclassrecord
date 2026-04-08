<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'learner_id',
        'section_id',
        'school_year_id',
        'status',
    ];

    public function learner()
    {
        return $this->belongsTo(Learner::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class);
    }
}
