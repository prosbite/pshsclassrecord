<?php

namespace Database\Factories;

use App\Models\ExerciseSessionQuestion;
use App\Models\ExerciseSessionQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseSessionQuestionOption>
 */
class ExerciseSessionQuestionOptionFactory extends Factory
{
    protected $model = ExerciseSessionQuestionOption::class;

    public function definition(): array
    {
        return [
            'exercise_session_question_id' => ExerciseSessionQuestion::factory(),
            'source_option_id' => null,
            'position' => 0,
            'label' => fake()->words(2, true),
            'is_correct' => false,
        ];
    }
}
