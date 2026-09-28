<?php

namespace Database\Factories;

use App\Models\ExerciseSession;
use App\Models\ExerciseSessionQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseSessionQuestion>
 */
class ExerciseSessionQuestionFactory extends Factory
{
    protected $model = ExerciseSessionQuestion::class;

    public function definition(): array
    {
        return [
            'exercise_session_id' => ExerciseSession::factory(),
            'source_question_id' => null,
            'source_questionnaire_id' => null,
            'type' => 'text',
            'position' => 0,
            'prompt_text' => fake()->sentence(),
            'image_path' => null,
            'points' => 1,
            'answer_key' => null,
            'score' => null,
            'graded_at' => null,
            'selected_option_id' => null,
            'response_text' => null,
        ];
    }

    public function graded(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
            'graded_at' => now(),
        ]);
    }
}
