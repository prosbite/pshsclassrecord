<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ExerciseSession;
use App\Models\Learner;
use App\Models\Questionnaire;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Services\AssessmentRemediationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExercisePageController extends Controller
{
    public function __construct(
        private readonly AssessmentRemediationService $remediation,
    ) {}

    public function index(Request $request)
    {
        $sections = $this->sectionTabs();

        $selectedSectionId = $this->resolveSectionId($request->query('section'), $sections);
        $assessments = $this->assessmentsFor($sections, $selectedSectionId);
        $selectedAssessmentId = $this->resolveAssessmentId($request->query('assessment'), $assessments);
        $kind = $this->resolveKind($request->query('kind'));

        $sessions = collect();

        if ($selectedAssessmentId !== null) {
            $sessions = ExerciseSession::query()
                ->where('assessment_id', $selectedAssessmentId)
                ->where('kind', $kind)
                ->with(['learner', 'questionnaires', 'sessionQuestions'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ExerciseSession $session) => [
                    'id' => $session->id,
                    'kind' => $session->kind,
                    'status' => $session->status,
                    'percent' => $session->percent,
                    'attempted_count' => $session->attempted_count,
                    'answered_count' => $session->answered_count,
                    'total_questions' => $session->sessionQuestions->count(),
                    'questionnaires_count' => $session->questionnaires->count(),
                    'learner' => [
                        'id' => $session->learner?->id,
                        'name_last_first' => $this->learnerName($session->learner),
                        'username' => $session->learner?->email,
                    ],
                ])
                ->values();
        }

        return Inertia::render('Exercises/Index', [
            'sections' => $sections,
            'selectedSectionId' => $selectedSectionId,
            'selectedAssessmentId' => $selectedAssessmentId,
            'kind' => $kind,
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request)
    {
        $sections = $this->sectionTabs();

        $selectedSectionId = $this->resolveSectionId($request->query('section'), $sections);
        $assessments = $this->assessmentsFor($sections, $selectedSectionId);
        $selectedAssessmentId = $this->requestedAssessmentId($request->query('assessment'), $assessments);

        $props = [
            'sections' => $sections,
            'selectedSectionId' => $selectedSectionId,
            'selectedAssessmentId' => $selectedAssessmentId,
        ];

        if ($selectedAssessmentId !== null) {
            $assessment = Assessment::with(['assessmentType', 'section.gradeLevel', 'quarter', 'questionnaires.topic'])
                ->find($selectedAssessmentId);

            if ($assessment) {
                $props['assessment'] = [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->assessmentType?->name,
                    'section' => $assessment->section?->section_name,
                    'quarter' => $assessment->quarter?->quarter,
                ];
                $props['failingLearners'] = $this->remediation->failingLearners($assessment);
                $props['threshold'] = Setting::passingThreshold();
                $props['poolQuestionnaires'] = $assessment->questionnaires;
                $props['bankQuestionnaires'] = Questionnaire::with('topic')
                    ->orderBy('topic_id')
                    ->orderBy('position')
                    ->orderBy('title')
                    ->get(['id', 'topic_id', 'title']);
            }
        }

        return Inertia::render('Exercises/Create', $props);
    }

    public function store(Request $request, Assessment $assessment)
    {
        $data = $request->validate([
            'learner_ids' => ['required', 'array'],
            'learner_ids.*' => ['integer', 'exists:learners,id'],
            'questionnaire_ids' => ['required', 'array', 'min:1'],
            'questionnaire_ids.*' => ['integer', 'exists:questionnaires,id'],
            'kind' => ['nullable', 'in:preventive,enhancement'],
        ]);

        $kind = $data['kind'] ?? ExerciseSession::KIND_PREVENTIVE;

        $result = $this->remediation->assignSessions(
            $assessment,
            $data['learner_ids'],
            $data['questionnaire_ids'],
            $request->user()->id,
            $kind,
        );

        $message = "{$result['created']} {$kind} session".($result['created'] === 1 ? '' : 's').' created';

        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} skipped (already assigned)";
        }

        return redirect()
            ->route('exercises.index', [
                'section' => $assessment->section_id,
                'assessment' => $assessment->id,
                'kind' => $kind,
            ])
            ->with('success', $message);
    }

    public function student(Learner $learner)
    {
        $sessions = $learner->exerciseSessions()
            ->with(['assessment.assessmentType', 'assessment.section', 'assessment.quarter', 'sessionQuestions'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExerciseSession $session) => [
                'id' => $session->id,
                'assessment' => [
                    'id' => $session->assessment?->id,
                    'title' => $session->assessment?->title,
                    'type' => $session->assessment?->assessmentType?->name,
                    'section' => $session->assessment?->section?->section_name,
                    'quarter' => $session->assessment?->quarter?->quarter,
                ],
                'kind' => $session->kind,
                'status' => $session->status,
                'answered_count' => $session->answered_count,
                'total_questions' => $session->sessionQuestions->count(),
                'attempted_count' => $session->attempted_count,
                'percent' => $session->percent,
                'created_at' => $session->created_at?->toIso8601String(),
                'completed_at' => $session->completed_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Exercises/Student', [
            'learner' => [
                'id' => $learner->id,
                'name' => $this->learnerName($learner),
                'email' => $learner->email,
            ],
            'sessions' => $sessions,
        ]);
    }

    public function destroy(Request $request, ExerciseSession $exerciseSession)
    {
        $assessment = $exerciseSession->assessment;
        $kind = $this->resolveKind($request->input('kind'));

        $exerciseSession->delete();

        return redirect()
            ->route('exercises.index', [
                'section' => $assessment?->section_id,
                'assessment' => $exerciseSession->assessment_id,
                'kind' => $kind,
            ])
            ->with('success', 'Session deleted');
    }

    public function destroyAll(Request $request, Assessment $assessment)
    {
        $count = $assessment->exerciseSessions()->count();

        $assessment->exerciseSessions()->delete();

        $message = "{$count} session".($count === 1 ? '' : 's').' deleted';

        return redirect()
            ->route('exercises.index', [
                'section' => $assessment->section_id,
                'assessment' => $assessment->id,
                'kind' => $this->resolveKind($request->input('kind')),
            ])
            ->with('success', $message);
    }

    /**
     * Sections in the current school year that have at least one assessment,
     * each with its nested assessments (title falls back to the type name).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sectionTabs(): array
    {
        $schoolYear = SchoolYear::current();

        if (! $schoolYear) {
            return [];
        }

        $assessments = Assessment::with(['assessmentType', 'quarter'])
            ->where('school_year_id', $schoolYear->id)
            ->whereNotNull('section_id')
            ->orderBy('quarter_id')
            ->orderBy('assessment_date')
            ->orderBy('id')
            ->get()
            ->groupBy('section_id');

        $sections = Section::with('gradeLevel')
            ->whereIn('id', $assessments->keys()->all())
            ->orderBy('grade_level_id')
            ->orderBy('section_name')
            ->get();

        return $sections->map(fn (Section $section) => [
            'id' => $section->id,
            'section_name' => $section->section_name,
            'grade_level' => $section->gradeLevel?->grade_level,
            'assessments' => $assessments->get($section->id, collect())
                ->map(fn (Assessment $assessment) => [
                    'id' => $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->assessmentType?->name,
                    'quarter' => $assessment->quarter?->quarter,
                    'assessment_date' => $assessment->assessment_date?->toDateString(),
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function resolveSectionId(mixed $requested, array $sections): ?int
    {
        $ids = collect($sections)->pluck('id');

        if ($requested !== null && $ids->contains((int) $requested)) {
            return (int) $requested;
        }

        return $sections[0]['id'] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function assessmentsFor(array $sections, ?int $sectionId): array
    {
        if ($sectionId === null) {
            return [];
        }

        $section = collect($sections)->firstWhere('id', $sectionId);

        return $section['assessments'] ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $assessments
     */
    private function resolveAssessmentId(mixed $requested, array $assessments): ?int
    {
        $ids = collect($assessments)->pluck('id');

        if ($requested !== null && $ids->contains((int) $requested)) {
            return (int) $requested;
        }

        return $assessments[0]['id'] ?? null;
    }

    /**
     * Only resolves an explicitly requested assessment that belongs to the
     * section; unlike the index, the create page starts with no selection.
     *
     * @param  array<int, array<string, mixed>>  $assessments
     */
    private function requestedAssessmentId(mixed $requested, array $assessments): ?int
    {
        if ($requested === null) {
            return null;
        }

        return collect($assessments)->pluck('id')->contains((int) $requested)
            ? (int) $requested
            : null;
    }

    private function resolveKind(mixed $kind): string
    {
        return $kind === ExerciseSession::KIND_ENHANCEMENT
            ? ExerciseSession::KIND_ENHANCEMENT
            : ExerciseSession::KIND_PREVENTIVE;
    }

    private function learnerName(?Learner $learner): ?string
    {
        if (! $learner) {
            return null;
        }

        return trim(collect([
            $learner->last_name,
            $learner->first_name,
            $learner->middle_name ? $learner->middle_name.'.' : null,
        ])->filter()->implode(', '), ' ,');
    }
}
