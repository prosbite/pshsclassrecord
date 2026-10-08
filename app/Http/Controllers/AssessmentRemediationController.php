<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ExerciseSession;
use App\Models\Questionnaire;
use App\Models\Setting;
use App\Models\Topic;
use App\Services\AssessmentRemediationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssessmentRemediationController extends Controller
{
    public function __construct(
        private readonly AssessmentRemediationService $remediation,
    ) {}

    public function show(Assessment $assessment)
    {
        $assessment->load([
            'assessmentType',
            'section.gradeLevel',
            'quarter',
            'topics',
            'questionnaires.topic',
        ]);

        $sessions = $assessment->exerciseSessions()
            ->with(['learner', 'questionnaires', 'sessionQuestions'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('Assessments/Remediation', [
            'assessment' => $assessment,
            'poolTopics' => $assessment->topics,
            'poolQuestionnaires' => $assessment->questionnaires,
            'bankTopics' => Topic::orderBy('name')->get(['id', 'name']),
            'bankQuestionnaires' => Questionnaire::with('topic')
                ->orderBy('topic_id')
                ->orderBy('position')
                ->orderBy('title')
                ->get(['id', 'topic_id', 'title']),
            'failingLearners' => $this->remediation->failingLearners($assessment),
            'threshold' => Setting::passingThreshold(),
            'sessions' => $sessions,
        ]);
    }

    public function storeSessions(Request $request, Assessment $assessment)
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
            ->route('assessments.remediation', $assessment)
            ->with('success', $message);
    }

    public function destroySessions(Assessment $assessment)
    {
        $count = $assessment->exerciseSessions()->count();

        $assessment->exerciseSessions()->delete();

        $message = "{$count} session".($count === 1 ? '' : 's').' deleted';

        return redirect()
            ->route('assessments.remediation', $assessment)
            ->with('success', $message);
    }
}
