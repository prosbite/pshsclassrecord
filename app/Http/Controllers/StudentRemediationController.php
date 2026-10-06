<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRemediationSubmitRequest;
use App\Models\ExerciseSession;
use App\Services\ExerciseSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class StudentRemediationController extends Controller
{
    public function __construct(
        private readonly ExerciseSessionService $sessions,
    ) {}

    public function index(Request $request)
    {
        $learner = $request->user()?->learner;

        $ongoing = collect();
        $completed = collect();

        if ($learner) {
            $sessions = $learner->exerciseSessions()
                ->with([
                    'assessment.assessmentType',
                    'assessment.quarter',
                    'sessionQuestions',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            foreach ($sessions as $session) {
                $row = $this->transformSummary($session);

                if ($session->status === 'completed') {
                    $completed->push($row);
                } else {
                    $ongoing->push($row);
                }
            }
        }

        return Inertia::render('Students/Remediation/Index', [
            'ongoing' => $ongoing->values()->all(),
            'completed' => $completed->values()->all(),
        ]);
    }

    public function show(Request $request, ExerciseSession $exerciseSession)
    {
        $this->abortUnlessOwner($request, $exerciseSession);

        $exerciseSession->load([
            'assessment.assessmentType',
            'assessment.quarter',
            'questionnaires.topic',
            'sessionQuestions.options',
        ]);

        $completed = $exerciseSession->status === 'completed';

        $questions = $exerciseSession->sessionQuestions
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn ($question) => $this->transformQuestion($question, $completed))
            ->all();

        $questionnaires = $exerciseSession->questionnaires
            ->map(fn ($questionnaire) => [
                'id' => $questionnaire->id,
                'title' => $questionnaire->title,
                'topic' => $questionnaire->topic?->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Students/Remediation/Show', [
            'session' => [
                'id' => $exerciseSession->id,
                'assessment' => [
                    'id' => $exerciseSession->assessment?->id,
                    'title' => $exerciseSession->assessment?->title,
                    'type' => $exerciseSession->assessment?->assessmentType?->name,
                    'quarter' => $exerciseSession->assessment?->quarter?->quarter,
                ],
                'status' => $exerciseSession->status,
                'kind' => $exerciseSession->kind,
                'submitted_at' => $exerciseSession->submitted_at?->toIso8601String(),
                'completed_at' => $exerciseSession->completed_at?->toIso8601String(),
                'answered_count' => $exerciseSession->answered_count,
                'total_questions' => count($questions),
                'percent' => $completed ? $exerciseSession->percent : null,
                'total_score' => $completed ? $exerciseSession->total_score : null,
                'max_score' => $completed ? $exerciseSession->max_score : null,
            ],
            'questionnaires' => $questionnaires,
            'sessionQuestions' => $questions,
        ]);
    }

    public function submit(StudentRemediationSubmitRequest $request, ExerciseSession $exerciseSession)
    {
        abort_unless($exerciseSession->status === 'assigned', Response::HTTP_FORBIDDEN);

        $this->sessions->submitAnswers($exerciseSession, $request->validated('answers') ?? []);

        return redirect()
            ->route('student.remediation.index')
            ->with('success', 'Answers submitted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformSummary(ExerciseSession $session): array
    {
        $completed = $session->status === 'completed';

        return [
            'id' => $session->id,
            'assessment' => [
                'id' => $session->assessment?->id,
                'title' => $session->assessment?->title,
                'type' => $session->assessment?->assessmentType?->name,
                'quarter' => $session->assessment?->quarter?->quarter,
            ],
            'status' => $session->status,
            'kind' => $session->kind,
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'answered_count' => $session->answered_count,
            'total_questions' => $session->sessionQuestions->count(),
            'percent' => $completed ? $session->percent : null,
        ];
    }

    /**
     * Student-safe allow-list: never expose `is_correct`, `answer_key`,
     * `source_option_id`, `source_question_id`, or marking before completion.
     *
     * @return array<string, mixed>
     */
    protected function transformQuestion($question, bool $completed): array
    {
        $payload = [
            'id' => $question->id,
            'questionnaire_id' => $question->source_questionnaire_id,
            'type' => $question->type,
            'position' => $question->position,
            'prompt_text' => $question->prompt_text,
            'image_url' => $question->image_url,
            'selected_option_id' => $question->selected_option_id,
            'response_text' => $question->response_text,
            'options' => $question->options
                ->map(fn ($option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'position' => $option->position,
                ])
                ->values()
                ->all(),
        ];

        if ($completed) {
            $payload['points'] = $question->points;
            $payload['score'] = $question->score !== null ? (float) $question->score : null;
        }

        return $payload;
    }

    protected function abortUnlessOwner(Request $request, ExerciseSession $exerciseSession): void
    {
        abort_unless(
            $exerciseSession->learner?->user_id === $request->user()?->id,
            Response::HTTP_FORBIDDEN
        );
    }
}
