<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssessmentPageController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Assessments/Index', $this->loadAssessmentsViewData($request));
    }

    public function summary(Request $request)
    {
        return Inertia::render('Assessments/Summary', $this->loadAssessmentsViewData($request));
    }

    public function create()
    {
        $schoolYear = SchoolYear::current();

        $assessmentTypes = AssessmentType::orderBy('name')->get();
        $quarters = Quarter::with('schoolYear')
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->orderBy('quarter')
            ->get();
        $sections = Section::with('gradeLevel')
            ->orderBy('grade_level_id')
            ->orderBy('section_name')
            ->get();

        return Inertia::render('Assessments/Create', [
            'schoolYear' => $schoolYear,
            'assessmentTypes' => $assessmentTypes,
            'quarters' => $quarters,
            'sections' => $sections,
        ]);
    }

    public function show(Assessment $assessment)
    {
        $assessment->load([
            'assessmentType',
            'quarter',
            'schoolYear',
            'section.gradeLevel',
            'user',
            'learners',
        ]);

        $learners = $assessment->learners->map(function ($learner) {
            return [
                'id' => $learner->id,
                'first_name' => $learner->first_name,
                'middle_name' => $learner->middle_name,
                'last_name' => $learner->last_name,
                'email' => $learner->email,
                'status' => $learner->status,
                'score' => $learner->pivot->score ?? null,
            ];
        });

        return Inertia::render('Assessments/Show', [
            'assessment' => $assessment,
            'learners' => $learners,
        ]);
    }

    private function loadAssessmentsViewData(Request $request): array
    {
        $schoolYear = SchoolYear::current();
        $sectionFilter = $request->query('section');

        $query = Assessment::with([
            'assessmentType',
            'quarter',
            'section.gradeLevel',
            'user',
            'learners',
        ])->withCount('learners');

        if ($schoolYear) {
            $query->where('school_year_id', $schoolYear->id);
        }

        if ($sectionFilter === 'unassigned') {
            $query->whereNull('section_id');
        } elseif ($sectionFilter && $sectionFilter !== 'all') {
            $query->where('section_id', $sectionFilter);
        }

        $assessments = $query->get()->sort(function ($a, $b) {
            $aQuarter = $a->quarter?->quarter ?? 0;
            $bQuarter = $b->quarter?->quarter ?? 0;

            if ($aQuarter !== $bQuarter) {
                return $aQuarter <=> $bQuarter;
            }

            $aCreated = $a->created_at?->timestamp ?? 0;
            $bCreated = $b->created_at?->timestamp ?? 0;

            return $bCreated <=> $aCreated;
        })->values();

        $sections = Section::with('gradeLevel')
            ->orderBy('grade_level_id')
            ->orderBy('section_name')
            ->get();

        $section = Section::with('enrollments.learner')
            ->find($sectionFilter);
        return [
            'assessments' => $assessments,
            'schoolYear' => $schoolYear,
            'sections' => $sections,
            'section' => $section,
            'sectionFilter' => $sectionFilter ?? 'all',
        ];
    }
}
