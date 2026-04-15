<?php

namespace Database\Seeders;

use Database\Seeders\AssessmentTypeSeeder;
use Database\Seeders\GradeLevelSectionSeeder;
use Database\Seeders\LearnerSeeder;
use Database\Seeders\QuarterSeeder;
use Database\Seeders\SchoolYearSeeder;
use Database\Seeders\StudentPasswordSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SchoolYearSeeder::class,
            QuarterSeeder::class,
            GradeLevelSectionSeeder::class,
            UserSeeder::class,
            StudentPasswordSeeder::class,
            // LearnerSeeder::class,
            AssessmentTypeSeeder::class,
        ]);
    }
}
