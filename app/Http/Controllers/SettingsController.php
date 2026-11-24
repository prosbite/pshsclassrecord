<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        $currentSchoolYear = SchoolYear::current();
        $currentQuarter = Quarter::current();

        $schoolYears = SchoolYear::query()
            ->with(['quarters' => fn ($query) => $query->orderBy('quarter')])
            ->orderByDesc('year_start')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolYear $schoolYear) => [
                'id' => $schoolYear->id,
                'year_start' => $schoolYear->year_start,
                'year_end' => $schoolYear->year_end,
                'status' => $schoolYear->status,
                'is_current' => $schoolYear->id === $currentSchoolYear?->id,
                'quarters' => $schoolYear->quarters->map(fn (Quarter $quarter) => [
                    'id' => $quarter->id,
                    'number' => $quarter->quarter,
                    'name' => $quarter->name,
                    'start_date' => $quarter->start_date,
                    'end_date' => $quarter->end_date,
                    'status' => $quarter->status,
                    'is_current' => $quarter->id === $currentQuarter?->id,
                ])->values(),
            ])->values();

        return Inertia::render('Settings/Edit', [
            'passingThreshold' => Setting::passingThreshold(),
            'schoolYears' => $schoolYears,
            'currentSchoolYearId' => $currentSchoolYear?->id,
            'currentQuarterId' => $currentQuarter?->id,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'passing_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::set('passing_threshold', $data['passing_threshold']);

        return redirect()->route('settings.edit')->with('success', 'Settings updated');
    }

    public function storeSchoolYear(Request $request)
    {
        $data = $request->validate([
            'year_start' => ['required', 'integer', 'min:2000', 'max:2100'],
            'year_end' => ['required', 'integer', 'min:2001', 'max:2101', 'gt:year_start'],
        ]);

        $schoolYear = SchoolYear::firstOrCreate(
            [
                'year_start' => (string) $data['year_start'],
                'year_end' => (string) $data['year_end'],
            ],
            ['status' => 'inactive'],
        );

        $this->provisionQuarters($schoolYear);

        $message = "School year {$schoolYear->year_start}-{$schoolYear->year_end} is ready.";

        if (! SchoolYear::current()) {
            $schoolYear->activate();
            $schoolYear->quarters()->orderBy('quarter')->first()?->activate();
            $message .= ' It is now the current school year.';
        }

        return redirect()->route('settings.edit')->with('success', $message);
    }

    public function activateSchoolYear(SchoolYear $schoolYear)
    {
        $schoolYear->activate();

        if (! Quarter::current()) {
            $schoolYear->quarters()->orderBy('quarter')->first()?->activate();
        }

        return redirect()
            ->route('settings.edit')
            ->with('success', "{$schoolYear->year_start}-{$schoolYear->year_end} is now the current school year.");
    }

    public function activateQuarter(Quarter $quarter)
    {
        $schoolYear = SchoolYear::current();

        if (! $schoolYear || $quarter->school_year_id !== $schoolYear->id) {
            return redirect()
                ->route('settings.edit')
                ->with('error', 'That quarter does not belong to the current school year.');
        }

        $quarter->activate();

        return redirect()
            ->route('settings.edit')
            ->with('success', "{$quarter->name} is now the current quarter.");
    }

    private function provisionQuarters(SchoolYear $schoolYear): void
    {
        $startYear = (int) $schoolYear->year_start;
        $endYear = (int) $schoolYear->year_end;

        $periods = [
            1 => ["{$startYear}-07-01", "{$startYear}-09-30"],
            2 => ["{$startYear}-10-01", "{$startYear}-12-31"],
            3 => ["{$endYear}-01-01", "{$endYear}-03-31"],
            4 => ["{$endYear}-04-01", "{$endYear}-06-30"],
        ];

        foreach ($periods as $number => [$startDate, $endDate]) {
            $schoolYear->quarters()->firstOrCreate(
                ['quarter' => $number],
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'inactive',
                ],
            );
        }
    }
}
