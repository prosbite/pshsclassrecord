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
        $schoolYear = SchoolYear::current();
        $sectionFilter = $request->query('section');

        $sections = Section::with('gradeLevel')
            ->orderBy('grade_level_id')
            ->orderBy('section_name')
            ->get();

        $quarters = Quarter::query()
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->orderBy('quarter')
            ->get();

        $section = null;
        $assessments = collect();

        if ($sectionFilter && ! in_array($sectionFilter, ['all', 'unassigned'], true)) {
            $section = Section::with([
                'gradeLevel',
                'enrollments' => function ($query) use ($schoolYear) {
                    $query->where('status', 'active')
                        ->when($schoolYear, fn ($inner) => $inner->where('school_year_id', $schoolYear->id))
                        ->with('learner');
                },
            ])->find($sectionFilter);

            if ($section) {
                $section->setRelation(
                    'enrollments',
                    $section->enrollments
                        ->sortBy(fn ($enrollment) => $enrollment->learner?->last_name)
                        ->values()
                );

                $assessments = Assessment::with([
                    'assessmentType:id,name,code',
                    'quarter:id,quarter,start_date,end_date,school_year_id',
                    'learners:id',
                ])
                    ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                    ->where('section_id', $section->id)
                    ->orderBy('quarter_id')
                    ->orderBy('assessment_date')
                    ->orderBy('id')
                    ->get();
            }
        }

        return Inertia::render('Assessments/Summary', [
            'assessments' => $assessments,
            'schoolYear' => $schoolYear,
            'sections' => $sections,
            'section' => $section,
            'sectionFilter' => $sectionFilter ?? 'all',
            'quarters' => $quarters,
            'selectedQuarterId' => $request->query('quarter') ?? $quarters->first()?->id,
        ]);
    }

    public function create()
    {
        return Inertia::render('Assessments/Create', $this->formOptions());
    }

    public function edit(Assessment $assessment)
    {
        $assessment->load(['assessmentType', 'quarter', 'schoolYear', 'section.gradeLevel', 'learners']);

        $learnerScores = $assessment->learners->mapWithKeys(fn ($learner) => [
            (string) $learner->id => $learner->pivot->score,
        ]);

        $learnerTentatives = $assessment->learners->mapWithKeys(fn ($learner) => [
            (string) $learner->id => (bool) ($learner->pivot->tentative ?? false),
        ]);

        return Inertia::render('Assessments/Edit', [
            ...$this->formOptions(),
            'assessment' => $assessment,
            'learnerScores' => $learnerScores,
            'learnerTentatives' => $learnerTentatives,
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
                'tentative' => (bool) ($learner->pivot->tentative ?? false),
            ];
        });

        return Inertia::render('Assessments/Show', [
            'assessment' => $assessment,
            'learners' => $learners,
        ]);
    }

    private function formOptions(): array
    {
        $schoolYear = SchoolYear::current();

        return [
            'schoolYear' => $schoolYear,
            'assessmentTypes' => AssessmentType::orderBy('name')->get(),
            'quarters' => Quarter::with('schoolYear')
                ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                ->orderBy('quarter')
                ->get(),
            'sections' => Section::with('gradeLevel')
                ->orderBy('grade_level_id')
                ->orderBy('section_name')
                ->get(),
        ];
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
