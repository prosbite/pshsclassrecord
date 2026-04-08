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
            'Grade 7' => ['Nova', 'Orion', 'Solstice'],
            'Grade 8' => ['Aurora', 'Vanguard', 'Zenith'],
            'Grade 9' => ['Aether', 'Beacon', 'Crest'],
            'Grade 10' => ['Summit', 'Pioneer', 'Harbor'],
            'Grade 11' => ['Stratus', 'Horizon', 'Catalyst'],
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
