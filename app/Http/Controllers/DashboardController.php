<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\ExerciseSession;
use App\Models\LoginActivity;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $schoolYear = SchoolYear::current();
        $threshold = Setting::passingThreshold();

        $activeLearners = Enrollment::query()
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->where('status', 'active')
            ->distinct()
            ->count('learner_id');

        $assessmentCount = Assessment::query()
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->count();

        $sessionCounts = ExerciseSession::query()
            ->when($schoolYear, fn ($query) => $query->whereHas(
                'assessment',
                fn ($assessment) => $assessment->where('school_year_id', $schoolYear->id)
            ))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pendingSessions = ExerciseSession::with([
            'learner:id,first_name,middle_name,last_name',
            'assessment:id,title,assessment_type_id,section_id,quarter_id',
            'assessment.assessmentType:id,name,code',
            'assessment.section:id,section_name',
            'assessment.quarter:id,quarter',
            'sessionQuestions:id,exercise_session_id,selected_option_id,response_text',
        ])
            ->where('status', 'submitted')
            ->when($schoolYear, fn ($query) => $query->whereHas(
                'assessment',
                fn ($assessment) => $assessment->where('school_year_id', $schoolYear->id)
            ))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (ExerciseSession $session) => [
                'id' => $session->id,
                'assessment_id' => $session->assessment?->id,
                'assessment_title' => $session->assessment?->title,
                'type' => $session->assessment?->assessmentType?->name,
                'section' => $session->assessment?->section?->section_name,
                'quarter' => $session->assessment?->quarter?->quarter,
                'kind' => $session->kind,
                'learner' => $session->learner ? [
                    'id' => $session->learner->id,
                    'name' => trim(collect([
                        $session->learner->last_name,
                        $session->learner->first_name,
                    ])->filter()->implode(', ')),
                ] : null,
                'submitted_at' => $session->submitted_at?->toISOString(),
                'answered_count' => $session->answered_count,
                'total_questions' => $session->sessionQuestions->count(),
            ])
            ->values()
            ->all();

        $recentAssessments = Assessment::with([
            'assessmentType:id,name,code',
            'section:id,section_name',
            'quarter:id,quarter',
        ])
            ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
            ->withCount('learners')
            ->orderByDesc('assessment_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $failingByAssessment = DB::table('assessment_learners as al')
            ->join('assessments as a', 'a.id', '=', 'al.assessment_id')
            ->whereIn('al.assessment_id', $recentAssessments->pluck('id')->all())
            ->whereNotNull('al.score')
            ->where('a.perfect_score', '>', 0)
            ->whereRaw('(al.score / a.perfect_score * 100) < ?', [$threshold])
            ->groupBy('al.assessment_id')
            ->selectRaw('al.assessment_id, count(*) as failing')
            ->pluck('failing', 'assessment_id');

        $recentLogins = LoginActivity::with('user:id,name,email,role')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (LoginActivity $activity) => [
                'id' => $activity->id,
                'name' => $activity->user_name ?: $activity->user?->name,
                'role' => $activity->user_role ?: $activity->user?->role,
                'actor_name' => $activity->actor?->name,
                'created_at' => $activity->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'summary' => [
                'school_year' => $schoolYear ? "{$schoolYear->year_start}-{$schoolYear->year_end}" : null,
                'active_learners' => $activeLearners,
                'sections' => Section::query()->count(),
                'assessments' => $assessmentCount,
                'passing_threshold' => $threshold,
            ],
            'counts' => [
                'topics' => Topic::query()->count(),
                'questionnaires' => Questionnaire::query()->count(),
                'questions' => Question::query()->count(),
            ],
            'remediation' => [
                'assigned' => (int) ($sessionCounts['assigned'] ?? 0),
                'submitted' => (int) ($sessionCounts['submitted'] ?? 0),
                'completed' => (int) ($sessionCounts['completed'] ?? 0),
                'pending' => $pendingSessions,
            ],
            'recentAssessments' => $recentAssessments
                ->map(fn (Assessment $assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->assessmentType?->name,
                    'section' => $assessment->section?->section_name,
                    'quarter' => $assessment->quarter?->quarter,
                    'assessment_date' => $assessment->assessment_date?->toDateString(),
                    'learners_count' => $assessment->learners_count,
                    'failing_count' => (int) ($failingByAssessment[$assessment->id] ?? 0),
                ])
                ->values()
                ->all(),
            'recentLogins' => $recentLogins,
        ]);
    }
}
