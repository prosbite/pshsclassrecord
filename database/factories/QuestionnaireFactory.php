<?php

namespace Database\Factories;

use App\Models\Questionnaire;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    protected $model = Questionnaire::class;

    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'title' => fake()->sentence(3),
            'instructions' => fake()->optional()->sentence(),
            'position' => 0,
        ];
    }
}
