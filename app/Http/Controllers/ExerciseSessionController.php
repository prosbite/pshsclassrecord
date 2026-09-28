<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExerciseSessionUpdateRequest;
use App\Models\ExerciseSession;
use App\Models\Questionnaire;
use App\Services\AssessmentRemediationService;
use App\Services\ExerciseSessionService;
use Inertia\Inertia;

class ExerciseSessionController extends Controller
{
    public function __construct(
        private readonly AssessmentRemediationService $remediation,
        private readonly ExerciseSessionService $sessions,
    ) {}

    public function show(ExerciseSession $exerciseSession)
    {
        $exerciseSession->load([
            'assessment.assessmentType',
            'assessment.section',
            'assessment.quarter',
            'learner',
            'questionnaires.topic',
        ]);

        // Admin-only payload: `is_correct` is intentionally exposed here but must
        // never reach student-facing serialization.
        $sessionQuestions = $exerciseSession->sessionQuestions()->with('options')->get();

        $availableQuestionnaires = Questionnaire::with('topic')
            ->orderBy('topic_id')
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'topic_id', 'title']);

        return Inertia::render('ExerciseSessions/Show', [
            'session' => $exerciseSession,
            'sessionQuestions' => $sessionQuestions,
            'questionnaires' => $exerciseSession->questionnaires,
            'availableQuestionnaires' => $availableQuestionnaires,
        ]);
    }

    public function update(ExerciseSessionUpdateRequest $request, ExerciseSession $exerciseSession)
    {
        $data = $request->validated();

        if ($request->has('questionnaire_ids')) {
            $this->remediation->syncSessionQuestionnaires(
                $exerciseSession,
                $data['questionnaire_ids'] ?? []
            );
        }

        if (isset($data['scores']) && is_array($data['scores'])) {
            $this->sessions->syncScores($exerciseSession, $data['scores']);
        }

        $status = $data['status'] ?? $exerciseSession->status;

        $exerciseSession->status = $status;
        $exerciseSession->remark = $data['remark'] ?? null;

        if ($status === 'completed') {
            $exerciseSession->completed_at = $exerciseSession->completed_at ?? now();
        } else {
            $exerciseSession->completed_at = null;
        }

        $exerciseSession->save();

        return redirect()
            ->route('exercise-sessions.show', $exerciseSession)
            ->with('success', 'Session updated');
    }

    public function destroy(ExerciseSession $exerciseSession)
    {
        $assessmentId = $exerciseSession->assessment_id;

        $exerciseSession->delete();

        return redirect()
            ->route('assessments.remediation', $assessmentId)
            ->with('success', 'Session deleted');
    }
}
