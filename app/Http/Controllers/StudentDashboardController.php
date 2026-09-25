<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Learner;
use App\Models\SchoolYear;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolYear = SchoolYear::current();

        $learner = $user?->learner;
        $section = null;
        $assessments = collect();

        if ($learner) {
            $enrollmentQuery = $learner->enrollments()->with(['section.gradeLevel']);

            if ($schoolYear) {
                $enrollmentQuery->where('school_year_id', $schoolYear->id);
            }

            $enrollment = $enrollmentQuery->latest('updated_at')->first();
            $section = $enrollment?->section;

            if ($section) {
                $assessments = Assessment::with([
                    'assessmentType:id,name,code',
                    'quarter:id,quarter,start_date,end_date,school_year_id',
                    'learners' => fn ($query) => $query->where('learners.id', $learner->id),
                ])
                    ->where('section_id', $section->id)
                    ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                    ->orderBy('quarter_id')
                    ->orderBy('assessment_date')
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($assessment) => $this->transformAssessment($assessment, $learner));
            }
        }

        return Inertia::render('Students/Dashboard', [
            'student' => $learner ? [
                'id' => $learner->id,
                'first_name' => $learner->first_name,
                'middle_name' => $learner->middle_name,
                'last_name' => $learner->last_name,
                'email' => $learner->email,
            ] : null,
            'section' => $section ? [
                'id' => $section->id,
                'section_name' => $section->section_name,
                'grade_level' => $section->gradeLevel?->grade_level,
            ] : null,
            'schoolYear' => $schoolYear ? [
                'id' => $schoolYear->id,
                'year_start' => $schoolYear->year_start,
                'year_end' => $schoolYear->year_end,
            ] : null,
            'assessments' => $assessments->values()->all(),
        ]);
    }

    protected function transformAssessment(Assessment $assessment, Learner $learner): array
    {
        $match = $assessment->learners->first();

        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'assessment_date' => $assessment->assessment_date?->toDateString(),
            'perfect_score' => $assessment->perfect_score,
            'quarter' => [
                'id' => $assessment->quarter?->id,
                'quarter' => $assessment->quarter?->quarter,
                'name' => $assessment->quarter?->name,
            ],
            'assessmentType' => [
                'id' => $assessment->assessmentType?->id,
                'name' => $assessment->assessmentType?->name,
                'code' => $assessment->assessmentType?->code,
            ],
            'score' => $match?->pivot?->score,
            'tentative' => (bool) ($match?->pivot?->tentative ?? false),
        ];
    }
}
