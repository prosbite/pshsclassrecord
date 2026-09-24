<?php

namespace App\Models;

use Database\Factories\QuarterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quarter extends Model
{
    /** @use HasFactory<QuarterFactory> */
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'quarter',
        'school_year_id',
        'status',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }
}
