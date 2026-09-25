<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'learner_id' => Learner::factory(),
            'section_id' => Section::factory(),
            'school_year_id' => SchoolYear::factory(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
