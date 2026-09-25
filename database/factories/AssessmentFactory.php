<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'assessment_type_id' => AssessmentType::factory(),
            'school_year_id' => SchoolYear::factory(),
            'quarter_id' => Quarter::factory(),
            'section_id' => Section::factory(),
            'user_id' => User::factory(),
            'assessment_date' => now()->toDateString(),
            'perfect_score' => 100,
        ];
    }

    public function forType(AssessmentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'assessment_type_id' => $type->id,
        ]);
    }

    public function withLearnerScores(array $scores): static
    {
        return $this->afterCreating(function (Assessment $assessment) use ($scores) {
            $assessment->learners()->sync($scores);
        });
    }
}
