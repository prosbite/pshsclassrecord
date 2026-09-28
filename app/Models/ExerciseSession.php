<?php

namespace App\Models;

use Database\Factories\ExerciseSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseSession extends Model
{
    /** @use HasFactory<ExerciseSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'learner_id',
        'created_by',
        'status',
        'remark',
        'completed_at',
        'submitted_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    protected $appends = [
        'attempted_count',
        'answered_count',
        'total_score',
        'max_score',
        'percent',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questionnaires(): BelongsToMany
    {
        return $this->belongsToMany(Questionnaire::class, 'exercise_session_questionnaire')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(ExerciseSessionQuestion::class, 'exercise_session_id')
            ->orderBy('position');
    }

    public function getAttemptedCountAttribute(): int
    {
        return $this->sessionQuestions->whereNotNull('score')->count();
    }

    public function getAnsweredCountAttribute(): int
    {
        return $this->sessionQuestions
            ->filter(fn ($question) => $question->selected_option_id !== null
                || ($question->response_text !== null && trim((string) $question->response_text) !== ''))
            ->count();
    }

    public function getTotalScoreAttribute(): float
    {
        return (float) $this->sessionQuestions
            ->whereNotNull('score')
            ->sum(fn ($question) => (float) $question->score);
    }

    public function getMaxScoreAttribute(): float
    {
        return (float) $this->sessionQuestions
            ->whereNotNull('score')
            ->sum(fn ($question) => (float) $question->points);
    }

    public function getPercentAttribute(): ?float
    {
        $max = $this->max_score;

        if ($max <= 0) {
            return null;
        }

        return round($this->total_score / $max * 100, 2);
    }
}
