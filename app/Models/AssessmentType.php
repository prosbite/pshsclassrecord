<?php

namespace App\Models;

use Database\Factories\AssessmentTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentType extends Model
{
    /** @use HasFactory<AssessmentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'percentage',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
