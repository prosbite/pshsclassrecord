<?php

namespace Database\Seeders;

use App\Models\Quarter;
use App\Models\SchoolYear;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuarterSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolYear = SchoolYear::current() ?? SchoolYear::firstOrCreate(
            ['year_start' => '2025', 'year_end' => '2026'],
            ['status' => 'active']
        );

        $basePeriods = [
            1 => ['start' => '2025-07-01', 'end' => '2025-09-30'],
            2 => ['start' => '2025-10-01', 'end' => '2025-12-31'],
            3 => ['start' => '2026-01-01', 'end' => '2026-03-31'],
            4 => ['start' => '2026-04-01', 'end' => '2026-06-30'],
        ];

        foreach ($basePeriods as $number => $dates) {
            Quarter::updateOrCreate(
                [
                    'quarter' => $number,
                    'school_year_id' => $schoolYear->id,
                ],
                [
                    'start_date' => Carbon::parse($dates['start']),
                    'end_date' => Carbon::parse($dates['end']),
                    'status' => $number === 1 ? 'active' : 'inactive',
                ]
            );
        }
    }
}
