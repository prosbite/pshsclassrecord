<?php

namespace Database\Seeders;

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
            SettingSeeder::class,
        ]);
    }
}
