<?php

namespace App\Http\Controllers;

use App\Models\Quarter;
use App\Models\QuarterlyAssessment;
use App\Models\Section;
use App\Models\SchoolYear;
use Inertia\Inertia;
use Illuminate\Http\Request;

class QuarterlyAssessmentPageController extends Controller
{
    public function index(Request $request)
    {
        $schoolYear = SchoolYear::current();

        $query = QuarterlyAssessment::with([
            'schoolYear',
            'quarter',
            'section.gradeLevel',
            'user',
        ])->orderBy('created_at', 'desc');

        if ($schoolYear) {
            $query->where('school_year_id', $schoolYear->id);
        }

        $quarterlyAssessments = $query->get();

        return Inertia::render('QuarterlyAssessments/Index', [
            'quarterlyAssessments' => $quarterlyAssessments,
            'schoolYear' => $schoolYear,
        ]);
    }

    public function upload()
    {
        $schoolYear = SchoolYear::current();

        $sections = Section::with('gradeLevel')
            ->orderBy('grade_level_id')
            ->orderBy('section_name')
            ->get();

        $quarters = Quarter::when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->orderBy('quarter')
            ->get();

        return Inertia::render('QuarterlyAssessments/Upload', [
            'schoolYear' => $schoolYear,
            'sections' => $sections,
            'quarters' => $quarters,
        ]);
    }

    public function show(QuarterlyAssessment $quarterlyAssessment)
    {
        $quarterlyAssessment->load([
            'schoolYear',
            'quarter',
            'section.gradeLevel',
            'user',
        ]);

        return Inertia::render('QuarterlyAssessments/Show', [
            'quarterlyAssessment' => $quarterlyAssessment,
        ]);
    }
}
