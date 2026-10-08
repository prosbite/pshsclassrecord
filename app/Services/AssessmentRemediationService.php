<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\ExerciseSession;
use App\Models\ExerciseSessionQuestion;
use App\Models\Learner;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\SchoolYear;
use App\Models\Setting;
use Illuminate\Support\Collection;

class AssessmentRemediationService
{
    /**
     * Active current-school-year learners of the assessment's section, with their
     * score on this assessment and whether they fall below the passing threshold.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function failingLearners(Assessment $assessment): Collection
    {
        $schoolYear = SchoolYear::current();
        $threshold = Setting::passingThreshold();
        $perfect = (int) ($assessment->perfect_score ?? 0);

        $enrollments = Enrollment::with('learner')
            ->where('enrollments.section_id', $assessment->section_id)
            ->when($schoolYear, fn ($query) => $query->where('enrollments.school_year_id', $schoolYear->id))
            ->where('enrollments.status', 'active')
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->orderBy('learners.last_name')
            ->select('enrollments.*')
            ->get();

        $pivots = $assessment->learners()
            ->get()
            ->mapWithKeys(fn ($learner) => [
                $learner->id => [
                    'score' => $learner->pivot->score,
                    'tentative' => (bool) ($learner->pivot->tentative ?? false),
                ],
            ]);

        return $enrollments->map(function ($enrollment) use ($pivots, $perfect, $threshold) {
            $learner = $enrollment->learner;
            $pivot = $pivots->get($learner?->id);

            $score = $pivot['score'] ?? null;
            $percent = null;

            if ($pivot !== null && $perfect > 0 && $score !== null) {
                $percent = round((float) $score / $perfect * 100, 2);
            }

            return [
                'learner' => $learner,
                'score' => $score !== null ? (float) $score : null,
                'tentative' => (bool) ($pivot['tentative'] ?? false),
                'percent' => $percent,
                'is_failing' => $percent !== null && $percent < $threshold,
            ];
        })->values();
    }

    /**
     * Assign one exercise session per learner for the assessment, deduped by
     * learner regardless of kind (a learner never has both a preventive and an
     * enhancement session for the same assessment). Questionnaires are frozen
     * into each newly created session.
     *
     * @param  array<int, int|string>  $learnerIds
     * @param  array<int, int|string>  $questionnaireIds
     * @return array{created: int, skipped: int}
     */
    public function assignSessions(
        Assessment $assessment,
        array $learnerIds,
        array $questionnaireIds,
        ?int $userId,
        string $kind = ExerciseSession::KIND_PREVENTIVE,
    ): array {
        $learnerIds = collect($learnerIds)->map(fn ($id) => (int) $id)->unique()->values();
        $questionnaireIds = collect($questionnaireIds)->map(fn ($id) => (int) $id)->unique()->values();

        $learners = Learner::query()->whereIn('id', $learnerIds->all())->get()->keyBy('id');

        $existing = ExerciseSession::query()
            ->where('assessment_id', $assessment->id)
            ->whereIn('learner_id', $learnerIds->all())
            ->pluck('learner_id')
            ->all();

        $created = 0;
        $skipped = 0;

        // Resolved lazily to avoid a constructor cycle: ExerciseSessionService
        // already depends on this service.
        $sessions = app(ExerciseSessionService::class);

        foreach ($learnerIds as $learnerId) {
            if (in_array($learnerId, array_map('intval', $existing), true)) {
                $skipped++;

                continue;
            }

            $learner = $learners->get($learnerId);

            if (! $learner) {
                continue;
            }

            $sessions->createFor($assessment, $learner, $questionnaireIds->all(), $userId, $kind);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Reconcile a session's frozen question snapshots with the submitted
     * questionnaire set. Retained source questions update in place so existing
     * scores survive; removed questionnaires drop their snapshot rows. A question
     * that appears in several selected questionnaires is snapshotted once.
     *
     * @param  array<int, int|string>  $questionnaireIds
     */
    public function syncSessionQuestionnaires(ExerciseSession $session, array $questionnaireIds): void
    {
        $requested = collect($questionnaireIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $questionnaires = Questionnaire::with([
            'questions' => fn ($query) => $query->orderBy('position')->orderBy('questions.id'),
            'questions.options',
        ])
            ->whereIn('id', $requested->all())
            ->get()
            ->keyBy('id');

        $desired = collect();

        foreach ($requested as $questionnaireId) {
            $questionnaire = $questionnaires->get($questionnaireId);

            if (! $questionnaire) {
                continue;
            }

            foreach ($questionnaire->questions as $question) {
                if ($desired->has($question->id)) {
                    continue;
                }

                $desired->put($question->id, [
                    'question' => $question,
                    'questionnaire_id' => $questionnaire->id,
                ]);
            }
        }

        $existing = $session->sessionQuestions()->get();
        $keptIds = [];
        $position = 0;

        foreach ($desired as $questionId => $entry) {
            $question = $entry['question'];

            $row = $existing->firstWhere('source_question_id', $questionId);

            $payload = [
                'source_questionnaire_id' => $entry['questionnaire_id'],
                'type' => $question->type,
                'position' => $position,
                'prompt_text' => $question->prompt_text,
                'image_path' => $question->image_path,
                'points' => $question->points,
                'answer_key' => $question->answer_key,
            ];

            if ($row) {
                $row->update($payload);
            } else {
                $row = $session->sessionQuestions()->create($payload + [
                    'source_question_id' => $question->id,
                ]);
            }

            $this->syncSnapshotOptions($row, $question);

            $keptIds[] = $row->id;
            $position++;
        }

        // Keep frozen rows whose bank question was deleted while its
        // questionnaire is still part of the session.
        $validQuestionnaireIds = $questionnaires->keys()->map(fn ($id) => (int) $id)->all();

        $orphans = $existing
            ->whereNull('source_question_id')
            ->whereIn('source_questionnaire_id', $validQuestionnaireIds)
            ->reject(fn ($row) => in_array($row->id, $keptIds, true));

        foreach ($orphans as $orphan) {
            $orphan->update(['position' => $position]);
            $keptIds[] = $orphan->id;
            $position++;
        }

        $session->sessionQuestions()->whereNotIn('id', $keptIds)->delete();

        $session->questionnaires()->sync(
            $questionnaires->keys()
                ->values()
                ->mapWithKeys(fn ($id, $index) => [(int) $id => ['position' => $index]])
                ->all()
        );
    }

    /**
     * Reconcile the frozen options of a snapshot row with the source question's
     * current options. Rows are matched by `source_option_id` so their ids stay
     * stable for a later student answer; positions follow the source order.
     */
    private function syncSnapshotOptions(ExerciseSessionQuestion $row, Question $question): void
    {
        $existing = $row->options()->get();
        $keptIds = [];

        foreach ($question->options as $position => $option) {
            $payload = [
                'source_option_id' => $option->id,
                'position' => $position,
                'label' => $option->label,
                'is_correct' => (bool) $option->is_correct,
            ];

            $snapshot = $existing->firstWhere('source_option_id', $option->id);

            if ($snapshot) {
                $snapshot->update($payload);
            } else {
                $snapshot = $row->options()->create($payload);
            }

            $keptIds[] = $snapshot->id;
        }

        $row->options()->whereNotIn('id', $keptIds)->delete();
    }
}
