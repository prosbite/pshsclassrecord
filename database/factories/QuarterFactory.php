<?php

namespace Database\Factories;

use App\Models\Quarter;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quarter>
 */
class QuarterFactory extends Factory
{
    protected $model = Quarter::class;

    public function definition(): array
    {
        $quarter = fake()->numberBetween(1, 4);
        $start = now()->startOfYear()->addMonths(($quarter - 1) * 3);

        return [
            'quarter' => $quarter,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonths(3)->subDay()->toDateString(),
            'school_year_id' => SchoolYear::factory(),
            'status' => 'inactive',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function number(int $quarter): static
    {
        return $this->state(fn (array $attributes) => [
            'quarter' => $quarter,
        ]);
    }
}
