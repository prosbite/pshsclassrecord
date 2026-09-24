<?php

namespace App\Models;

use Database\Factories\GradeLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    /** @use HasFactory<GradeLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'grade_level',
        'status',
    ];

    public function sections()
    {
        return $this->hasMany(Section::class);
    }
}
