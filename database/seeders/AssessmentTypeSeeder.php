<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AssessmentTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Long Test' => ['percentage' => 25, 'code' => 'long_test'],
            'Alternative Assessment' => ['percentage' => 25, 'code' => 'alternative'],
            'Formative Assessment' => ['percentage' => 25, 'code' => 'formative'],
        ];

        foreach ($types as $name => $attributes) {
            AssessmentType::updateOrCreate(
                ['name' => $name],
                $attributes
            );
        }
    }
}
