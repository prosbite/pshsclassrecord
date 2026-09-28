<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\ExerciseSession;
use App\Models\Learner;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseSession>
 */
class ExerciseSessionFactory extends Factory
{
    protected $model = ExerciseSession::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'learner_id' => Learner::factory(),
            'created_by' => User::factory(),
            'status' => 'assigned',
            'remark' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function withQuestions(int $count = 1, ?Questionnaire $questionnaire = null): static
    {
        return $this->afterCreating(function (ExerciseSession $session) use ($count, $questionnaire) {
            $questionnaire ??= Questionnaire::factory()->create();

            $questions = Question::factory()->count($count)->create([
                'topic_id' => $questionnaire->topic_id,
            ]);

            $questions->values()->each(function (Question $question, int $index) use ($questionnaire, $session) {
                $questionnaire->questions()->syncWithoutDetaching([
                    $question->id => ['position' => $index],
                ]);

                $session->questionnaires()->syncWithoutDetaching([
                    $questionnaire->id => ['position' => 0],
                ]);

                $session->sessionQuestions()->create([
                    'source_question_id' => $question->id,
                    'source_questionnaire_id' => $questionnaire->id,
                    'type' => $question->type,
                    'position' => $index,
                    'prompt_text' => $question->prompt_text,
                    'image_path' => $question->image_path,
                    'points' => $question->points,
                    'answer_key' => $question->answer_key,
                ]);
            });
        });
    }

    public function withMcq(int $optionCount = 3, ?Questionnaire $questionnaire = null): static
    {
        return $this->afterCreating(function (ExerciseSession $session) use ($optionCount, $questionnaire) {
            $questionnaire ??= Questionnaire::factory()->create();

            $question = Question::factory()->create([
                'topic_id' => $questionnaire->topic_id,
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'answer_key' => null,
            ]);

            $question->options()->createMany(
                collect(range(0, $optionCount - 1))
                    ->map(fn (int $index) => [
                        'position' => $index,
                        'label' => 'Option '.($index + 1),
                        'is_correct' => $index === 0,
                    ])
                    ->all()
            );

            $questionnaire->questions()->syncWithoutDetaching([
                $question->id => ['position' => 0],
            ]);

            $session->questionnaires()->syncWithoutDetaching([
                $questionnaire->id => ['position' => 0],
            ]);

            $row = $session->sessionQuestions()->create([
                'source_question_id' => $question->id,
                'source_questionnaire_id' => $questionnaire->id,
                'type' => $question->type,
                'position' => 0,
                'prompt_text' => $question->prompt_text,
                'image_path' => $question->image_path,
                'points' => $question->points,
                'answer_key' => null,
            ]);

            foreach ($question->options as $position => $option) {
                $row->options()->create([
                    'source_option_id' => $option->id,
                    'position' => $position,
                    'label' => $option->label,
                    'is_correct' => (bool) $option->is_correct,
                ]);
            }
        });
    }
}
