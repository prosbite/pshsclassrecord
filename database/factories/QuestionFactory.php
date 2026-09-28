<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'type' => 'text',
            'position' => 0,
            'prompt_text' => fake()->sentence(),
            'image_path' => null,
            'points' => 1,
            'answer_key' => fake()->optional()->word(),
        ];
    }
}
