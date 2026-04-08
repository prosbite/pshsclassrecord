<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SchoolYear::query()->update(['status' => 'inactive']);

        SchoolYear::updateOrCreate(
            ['year_start' => '2025', 'year_end' => '2026'],
            ['status' => 'active']
        );
    }
}
