<?php

namespace Database\Factories;

use App\Models\AssessmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentType>
 */
class AssessmentTypeFactory extends Factory
{
    protected $model = AssessmentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => null,
            'percentage' => 25,
        ];
    }

    public function longTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Long Test',
            'code' => 'long_test',
        ]);
    }

    public function alternative(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Alternative Assessment',
            'code' => 'alternative',
        ]);
    }

    public function formative(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Formative Assessment',
            'code' => 'formative',
        ]);
    }
}
