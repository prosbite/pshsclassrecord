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
            'Long Test' => 25,
            'Alternative Assessment' => 25,
            'Formative Assessment' => 25,
        ];

        foreach ($types as $name => $percentage) {
            AssessmentType::updateOrCreate(
                ['name' => $name],
                ['percentage' => $percentage]
            );
        }
    }
}
