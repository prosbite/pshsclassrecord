<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LearnerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $learners = [
            [
                'first_name' => 'Maris',
                'middle_name' => 'L.',
                'last_name' => 'Aurelio',
                'email' => 'maris.aurelio@pshs.edu.ph',
                'gender' => 'Female',
                'grade_level' => 'Grade 11',
                'section' => 'Stratus',
            ],
            [
                'first_name' => 'Noel',
                'middle_name' => 'R.',
                'last_name' => 'Rey',
                'email' => 'noel.rey@pshs.edu.ph',
                'gender' => 'Male',
                'grade_level' => 'Grade 12',
                'section' => 'Orosa',
            ],
            [
                'first_name' => 'Leah',
                'middle_name' => 'S.',
                'last_name' => 'Santos',
                'email' => 'leah.santos@pshs.edu.ph',
                'gender' => 'Female',
                'grade_level' => 'Grade 11',
                'section' => 'Catalyst',
            ],
        ];

        $schoolYear = SchoolYear::current();

        if (! $schoolYear) {
            $yearStart = now()->year;
            $schoolYear = SchoolYear::create([
                'year_start' => $yearStart,
                'year_end' => $yearStart + 1,
                'status' => 'active',
            ]);
        }

        foreach ($learners as $data) {
            $section = Section::where('section_name', $data['section'])
                ->first();

            if (! $section) {
                continue;
            }

            /** @var Learner $learner */
            $learner = Learner::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'last_name' => $data['last_name'],
                    'gender' => $data['gender'],
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            Enrollment::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'school_year_id' => $schoolYear->id,
                ],
                [
                    'section_id' => $section->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
