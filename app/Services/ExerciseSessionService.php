<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\ExerciseSession;
use App\Models\Learner;
use App\Models\Question;

class ExerciseSessionService
{
    public function __construct(
        private readonly AssessmentRemediationService $remediation,
    ) {}

    /**
     * Create (or reuse) the one session per learner/assessment and freeze the
     * selected questionnaires' questions into it.
     *
     * @param  array<int, int|string>  $questionnaireIds
     */
    public function createFor(Assessment $assessment, Learner $learner, array $questionnaireIds, ?int $userId): ExerciseSession
    {
        $session = ExerciseSession::firstOrCreate(
            [
                'assessment_id' => $assessment->id,
                'learner_id' => $learner->id,
            ],
            [
                'created_by' => $userId,
                'status' => 'assigned',
            ]
        );

        $this->remediation->syncSessionQuestionnaires($session, $questionnaireIds);

        return $session;
    }

    /**
     * Build the frozen snapshot payload for a question (content only, no score).
     * MCQ options are child rows reconciled separately by the sync path, not
     * part of this array payload.
     *
     * @return array<string, mixed>
     */
    public function snapshotQuestion(Question $question, int $sessionId, int $position, ?int $questionnaireId = null): array
    {
        return [
            'exercise_session_id' => $sessionId,
            'source_question_id' => $question->id,
            'source_questionnaire_id' => $questionnaireId,
            'type' => $question->type,
            'position' => $position,
            'prompt_text' => $question->prompt_text,
            'image_path' => $question->image_path,
            'points' => $question->points,
            'answer_key' => $question->answer_key,
        ];
    }

    /**
     * Apply a student's submission. The payload is treated as the complete set
     * for the session: any snapshot question absent from it is cleared. MCQ
     * answers are auto-scored; free-text answers never touch the teacher's mark.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function submitAnswers(ExerciseSession $session, array $entries): void
    {
        $byQuestion = collect($entries)
            ->filter(fn ($entry) => isset($entry['session_question_id']))
            ->keyBy(fn ($entry) => (int) $entry['session_question_id']);

        foreach ($session->sessionQuestions()->with('options')->get() as $question) {
            $entry = $byQuestion->get($question->id);

            $rawOption = $entry['selected_option_id'] ?? null;
            $selectedOptionId = ($rawOption === null || $rawOption === '') ? null : (int) $rawOption;

            $responseText = trim((string) ($entry['response_text'] ?? ''));
            $responseText = $responseText === '' ? null : $responseText;

            $payload = [
                'selected_option_id' => $selectedOptionId,
                'response_text' => $responseText,
            ];

            if ($question->type === Question::TYPE_MULTIPLE_CHOICE) {
                if ($selectedOptionId !== null) {
                    $isCorrect = (bool) optional(
                        $question->options->firstWhere('id', $selectedOptionId)
                    )->is_correct;

                    $payload['score'] = $isCorrect ? $question->points : 0;
                    $payload['graded_at'] = now();
                } else {
                    $payload['score'] = null;
                    $payload['graded_at'] = null;
                }
            }

            $question->update($payload);
        }

        $session->submitted_at = $session->submitted_at ?? now();
        $session->status = 'submitted';
        $session->save();
    }

    /**
     * Apply per-question marks. A blank or omitted score clears the mark; any
     * numeric value (including zero) stores and timestamps the grade.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    public function syncScores(ExerciseSession $session, array $entries): void
    {
        foreach ($entries as $entry) {
            $questionId = $entry['session_question_id'] ?? null;

            if ($questionId === null || $questionId === '') {
                continue;
            }

            $question = $session->sessionQuestions()->whereKey((int) $questionId)->first();

            if (! $question) {
                continue;
            }

            $raw = $entry['score'] ?? null;
            $score = ($raw === null || $raw === '') ? null : (float) $raw;

            $question->update([
                'score' => $score,
                'graded_at' => $score === null ? null : now(),
            ]);
        }
    }
}
