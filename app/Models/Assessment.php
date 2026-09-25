<?php

namespace App\Models;

use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'assessment_type_id',
        'school_year_id',
        'quarter_id',
        'section_id',
        'user_id',
        'assessment_date',
        'perfect_score',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'perfect_score' => 'integer',
    ];

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

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

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'assessment_learners')
            ->withPivot('score', 'tentative')
            ->withTimestamps();
    }
}
