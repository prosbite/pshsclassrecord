<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuarterlyAssessment extends Model
{
    protected $fillable = [
        'assessment',
        'school_year_id',
        'quarter_id',
        'section_id',
        'user_id',
    ];

    protected $casts = [
        'assessment' => 'array',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function quarter(): BelongsTo
    {
        return $this->belongsTo(Quarter::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
