<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GradeLevelSectionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gradeLevels = [
            'Grade 12' => ['Del Mundo', 'Orosa', 'Zara'],
        ];

        foreach ($gradeLevels as $level => $sections) {
            $gradeLevel = GradeLevel::updateOrCreate(
                ['grade_level' => $level],
                ['status' => 'active']
            );

            foreach ($sections as $section) {
                Section::updateOrCreate(
                    [
                        'section_name' => $section,
                        'grade_level_id' => $gradeLevel->id,
                    ],
                    ['status' => 'active']
                );
            }
        }
    }
}
