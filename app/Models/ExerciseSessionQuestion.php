<?php

namespace App\Models;

use Database\Factories\ExerciseSessionQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ExerciseSessionQuestion extends Model
{
    /** @use HasFactory<ExerciseSessionQuestionFactory> */
    use HasFactory;

    protected $fillable = [
        'exercise_session_id',
        'source_question_id',
        'source_questionnaire_id',
        'type',
        'position',
        'prompt_text',
        'image_path',
        'points',
        'answer_key',
        'score',
        'graded_at',
        'selected_option_id',
        'response_text',
    ];

    protected $casts = [
        'points' => 'integer',
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
        'selected_option_id' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExerciseSession::class, 'exercise_session_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    public function options(): HasMany
    {
        return $this->hasMany(ExerciseSessionQuestionOption::class, 'exercise_session_question_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(ExerciseSessionQuestionOption::class, 'selected_option_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    public function sourceQuestionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class, 'source_questionnaire_id');
    }
}
