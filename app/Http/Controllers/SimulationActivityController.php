<?php

namespace App\Http\Controllers;

use App\Models\SchoolYear;
use App\Models\SimulationActivity;
use Illuminate\Http\Request;

class SimulationActivityController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        $user = $request->user();
        $learner = $user?->learner;
        $schoolYear = SchoolYear::current();

        $sectionId = null;

        if ($learner) {
            $enrollment = $learner->enrollments()
                ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                ->latest('updated_at')
                ->first();

            $sectionId = $enrollment?->section_id;
        }

        SimulationActivity::create([
            'user_id' => $user?->id,
            'learner_id' => $learner?->id,
            'section_id' => $sectionId,
            'school_year_id' => $schoolYear?->id,
            'quarter' => $data['quarter'] ?? null,
        ]);

        return response()->noContent();
    }
}
