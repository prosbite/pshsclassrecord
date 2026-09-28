<?php

namespace App\Models;

use Database\Factories\ExerciseSessionQuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSessionQuestionOption extends Model
{
    /** @use HasFactory<ExerciseSessionQuestionOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'exercise_session_question_id',
        'source_option_id',
        'position',
        'label',
        'is_correct',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_correct' => 'boolean',
    ];

    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(ExerciseSessionQuestion::class, 'exercise_session_question_id');
    }

    public function sourceOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'source_option_id');
    }
}
