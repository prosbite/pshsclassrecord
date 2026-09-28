<?php

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\ExerciseSession;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\Quarter;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Services\ExerciseSessionService;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $this->gradeLevel = GradeLevel::factory()->create();
    $this->section = Section::factory()->create(['grade_level_id' => $this->gradeLevel->id]);

    $this->quarters = collect([1, 2, 3, 4])->mapWithKeys(fn ($number) => [
        $number => Quarter::factory()->create([
            'quarter' => $number,
            'school_year_id' => $this->schoolYear->id,
        ]),
    ]);

    $this->types = [
        'long_test' => AssessmentType::factory()->longTest()->create(),
        'alternative' => AssessmentType::factory()->alternative()->create(),
        'formative' => AssessmentType::factory()->formative()->create(),
    ];

    $this->admin = User::factory()->create();
    $this->admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    $this->learners = Learner::factory()->count(2)->create();

    foreach ($this->learners as $learner) {
        Enrollment::factory()->create([
            'learner_id' => $learner->id,
            'section_id' => $this->section->id,
            'school_year_id' => $this->schoolYear->id,
            'status' => 'active',
        ]);
    }

    $this->topic = Topic::factory()->create();
    $this->questionnaire = Questionnaire::factory()->create(['topic_id' => $this->topic->id]);

    $this->assessment = makeRemediationAssessment($this);
});

function makeRemediationAssessment($test, array $overrides = []): Assessment
{
    return Assessment::factory()->create(array_merge([
        'assessment_type_id' => $test->types['long_test']->id,
        'school_year_id' => $test->schoolYear->id,
        'quarter_id' => $test->quarters[1]->id,
        'section_id' => $test->section->id,
        'user_id' => $test->admin->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
    ], $overrides));
}

function addRemediationQuestion(Questionnaire $questionnaire, array $overrides = []): Question
{
    $question = Question::factory()->create(array_merge([
        'topic_id' => $questionnaire->topic_id,
    ], $overrides));

    $questionnaire->questions()->attach($question->id, [
        'position' => $questionnaire->questions()->count(),
    ]);

    return $question;
}

function addRemediationQuestions(Questionnaire $questionnaire, int $count, array $overrides = []): Collection
{
    return collect(range(1, $count))->map(fn () => addRemediationQuestion($questionnaire, $overrides));
}

function addRemediationMcq(Questionnaire $questionnaire, array $labels = ['A', 'B', 'C'], int $correctIndex = 0): Question
{
    $question = Question::factory()->create([
        'topic_id' => $questionnaire->topic_id,
        'type' => 'multiple_choice',
        'answer_key' => null,
    ]);

    foreach (array_values($labels) as $index => $label) {
        QuestionOption::factory()->create([
            'question_id' => $question->id,
            'position' => $index,
            'label' => $label,
            'is_correct' => $index === $correctIndex,
        ]);
    }

    $questionnaire->questions()->attach($question->id, [
        'position' => $questionnaire->questions()->count(),
    ]);

    return $question;
}

function failingRow($rows, int $learnerId): ?array
{
    return collect($rows)->first(fn ($row) => ($row['learner']['id'] ?? null) === $learnerId);
}

test('settings can be updated and the passing threshold changes', function () {
    expect(Setting::passingThreshold())->toBe(75.0);

    $this->actingAs($this->admin)->put(route('settings.update'), [
        'passing_threshold' => 80,
    ])->assertRedirect(route('settings.edit'));

    expect(Setting::passingThreshold())->toBe(80.0)
        ->and(Setting::get('passing_threshold'))->toEqual('80');
});

test('the passing threshold must be numeric within range', function () {
    $this->actingAs($this->admin)->put(route('settings.update'), [
        'passing_threshold' => 150,
    ])->assertSessionHasErrors('passing_threshold');

    $this->actingAs($this->admin)->put(route('settings.update'), [
        'passing_threshold' => 'abc',
    ])->assertSessionHasErrors('passing_threshold');
});

test('the settings page renders for admins', function () {
    $this->actingAs($this->admin)->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('passingThreshold', fn ($value) => (float) $value === 75.0)
        );
});

test('the remediation page flags failing learners and respects the threshold', function () {
    $this->assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 60, 'tentative' => false],
        $this->learners[1]->id => ['score' => 85, 'tentative' => false],
    ]);

    $this->actingAs($this->admin)->get(route('assessments.remediation', $this->assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Remediation')
            ->where('threshold', 75)
            ->where('failingLearners', function ($rows) {
                $failing = failingRow($rows, $this->learners[0]->id);
                $passing = failingRow($rows, $this->learners[1]->id);

                return $failing['is_failing'] === true
                    && (float) $failing['percent'] === 60.0
                    && $passing['is_failing'] === false
                    && (float) $passing['percent'] === 85.0;
            })
        );

    Setting::set('passing_threshold', 50);

    $this->actingAs($this->admin)->get(route('assessments.remediation', $this->assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('threshold', 50)
            ->where('failingLearners', function ($rows) {
                return failingRow($rows, $this->learners[0]->id)['is_failing'] === false;
            })
        );
});

test('a learner without a score row is not flagged', function () {
    $this->assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 10, 'tentative' => false],
    ]);

    $this->actingAs($this->admin)->get(route('assessments.remediation', $this->assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('failingLearners', function ($rows) {
                $row = failingRow($rows, $this->learners[1]->id);

                return $row['score'] === null
                    && $row['percent'] === null
                    && $row['is_failing'] === false;
            })
        );
});

test('a tentative failing learner is flagged with the tentative marker', function () {
    $this->assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 60, 'tentative' => true],
    ]);

    $this->actingAs($this->admin)->get(route('assessments.remediation', $this->assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('failingLearners', function ($rows) {
                $row = failingRow($rows, $this->learners[0]->id);

                return $row['tentative'] === true && $row['is_failing'] === true;
            })
        );
});

test('sessions can be created for any learner in the section', function () {
    addRemediationQuestion($this->questionnaire, ['prompt_text' => 'Item 1']);

    $this->actingAs($this->admin)->post(
        route('assessments.remediation.sessions.store', $this->assessment),
        [
            'learner_ids' => [$this->learners[1]->id],
            'questionnaire_ids' => [$this->questionnaire->id],
        ]
    )->assertRedirect(route('assessments.remediation', $this->assessment));

    expect(ExerciseSession::where('assessment_id', $this->assessment->id)
        ->where('learner_id', $this->learners[1]->id)
        ->exists())->toBeTrue();
});

test('creating sessions snapshots questions and skips duplicates', function () {
    addRemediationQuestions($this->questionnaire, 2);

    $payload = [
        'learner_ids' => [$this->learners[0]->id],
        'questionnaire_ids' => [$this->questionnaire->id],
    ];

    $this->actingAs($this->admin)
        ->post(route('assessments.remediation.sessions.store', $this->assessment), $payload)
        ->assertRedirect(route('assessments.remediation', $this->assessment));

    $session = ExerciseSession::where('assessment_id', $this->assessment->id)
        ->where('learner_id', $this->learners[0]->id)
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->sessionQuestions)->toHaveCount(2)
        ->and($session->questionnaires)->toHaveCount(1);

    $this->actingAs($this->admin)
        ->post(route('assessments.remediation.sessions.store', $this->assessment), $payload)
        ->assertRedirect(route('assessments.remediation', $this->assessment));

    expect(ExerciseSession::where('assessment_id', $this->assessment->id)->count())->toBe(1);
});

test('session scores are stored and totals use attempted questions only', function () {
    $first = addRemediationQuestion($this->questionnaire, [
        'position' => 0,
        'prompt_text' => 'Item 1',
        'points' => 2,
    ]);
    $second = addRemediationQuestion($this->questionnaire, [
        'position' => 1,
        'prompt_text' => 'Item 2',
        'points' => 4,
    ]);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $rows = $session->sessionQuestions;
    $firstRow = $rows->firstWhere('source_question_id', $first->id);
    $secondRow = $rows->firstWhere('source_question_id', $second->id);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'scores' => [
            ['session_question_id' => $firstRow->id, 'score' => 1.5],
            ['session_question_id' => $secondRow->id, 'score' => null],
        ],
        'status' => 'assigned',
    ])->assertRedirect(route('exercise-sessions.show', $session));

    expect((float) $firstRow->fresh()->score)->toBe(1.5)
        ->and($firstRow->fresh()->graded_at)->not->toBeNull()
        ->and($secondRow->fresh()->score)->toBeNull()
        ->and($secondRow->fresh()->graded_at)->toBeNull();

    $session = $session->fresh();
    $session->load('sessionQuestions');

    expect($session->attempted_count)->toBe(1)
        ->and((float) $session->total_score)->toBe(1.5)
        ->and((float) $session->max_score)->toBe(2.0)
        ->and((float) $session->percent)->toBe(75.0);
});

test('a blank score can clear a previously graded question', function () {
    $question = addRemediationQuestion($this->questionnaire, ['points' => 3]);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $row = $session->sessionQuestions->firstWhere('source_question_id', $question->id);
    $row->update(['score' => 2, 'graded_at' => now()]);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'scores' => [
            ['session_question_id' => $row->id, 'score' => ''],
        ],
    ])->assertRedirect(route('exercise-sessions.show', $session));

    expect($row->fresh()->score)->toBeNull()
        ->and($row->fresh()->graded_at)->toBeNull();
});

test('removing a questionnaire deletes its snapshot rows', function () {
    addRemediationQuestions($this->questionnaire, 2);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    expect($session->sessionQuestions()->count())->toBe(2);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [],
    ])->assertRedirect(route('exercise-sessions.show', $session));

    expect($session->sessionQuestions()->count())->toBe(0)
        ->and($session->questionnaires()->count())->toBe(0);
});

test('adding a questionnaire preserves existing scores', function () {
    $secondQuestionnaire = Questionnaire::factory()->create(['topic_id' => $this->topic->id]);
    $first = addRemediationQuestion($this->questionnaire, ['points' => 2]);
    addRemediationQuestion($secondQuestionnaire, ['points' => 3]);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $row = $session->sessionQuestions->firstWhere('source_question_id', $first->id);
    $row->update(['score' => 2, 'graded_at' => now()]);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id, $secondQuestionnaire->id],
    ])->assertRedirect(route('exercise-sessions.show', $session));

    $session = $session->fresh();
    $session->load('sessionQuestions');

    expect($session->sessionQuestions)->toHaveCount(2)
        ->and((float) $session->sessionQuestions->firstWhere('source_question_id', $first->id)->score)->toBe(2.0);
});

test('a question reused in two selected questionnaires is snapshotted once', function () {
    $shared = addRemediationQuestion($this->questionnaire, ['points' => 2]);
    $secondQuestionnaire = Questionnaire::factory()->create(['topic_id' => $this->topic->id]);
    $secondQuestionnaire->questions()->attach($shared->id, ['position' => 0]);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $row = $session->sessionQuestions->firstWhere('source_question_id', $shared->id);
    $row->update(['score' => 2, 'graded_at' => now()]);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id, $secondQuestionnaire->id],
    ])->assertRedirect(route('exercise-sessions.show', $session));

    $session = $session->fresh();
    $session->load('sessionQuestions');

    expect($session->sessionQuestions)->toHaveCount(1)
        ->and((float) $session->sessionQuestions->first()->score)->toBe(2.0);
});

test('scores cannot target another session question', function () {
    addRemediationQuestion($this->questionnaire);

    $firstSession = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);
    $secondSession = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[1], [$this->questionnaire->id], $this->admin->id);

    $foreignRow = $firstSession->sessionQuestions()->first();

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $secondSession), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'scores' => [
            ['session_question_id' => $foreignRow->id, 'score' => 1],
        ],
    ])->assertSessionHasErrors('scores.0.session_question_id');
});

test('a session stores its remark and completion status', function () {
    addRemediationQuestion($this->questionnaire);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'remark' => 'Needs more practice',
        'status' => 'completed',
    ])->assertRedirect(route('exercise-sessions.show', $session));

    $session = $session->fresh();

    expect($session->status)->toBe('completed')
        ->and($session->remark)->toBe('Needs more practice')
        ->and($session->completed_at)->not->toBeNull();

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'remark' => 'Reopened',
        'status' => 'assigned',
    ])->assertRedirect(route('exercise-sessions.show', $session));

    expect($session->fresh()->completed_at)->toBeNull()
        ->and($session->fresh()->status)->toBe('assigned');
});

test('deleting a session cascades its snapshot rows', function () {
    addRemediationQuestions($this->questionnaire, 2);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $sessionId = $session->id;

    $this->actingAs($this->admin)
        ->delete(route('exercise-sessions.destroy', $session))
        ->assertRedirect(route('assessments.remediation', $this->assessment));

    expect(ExerciseSession::find($sessionId))->toBeNull();
    $this->assertDatabaseMissing('exercise_session_questions', ['exercise_session_id' => $sessionId]);
});

test('the session page exposes frozen questions and totals', function () {
    addRemediationQuestions($this->questionnaire, 2);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $this->actingAs($this->admin)->get(route('exercise-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExerciseSessions/Show')
            ->where('session.id', $session->id)
            ->has('sessionQuestions', 2)
            ->has('questionnaires', 1)
        );
});

test('creating a session snapshots the mcq type and options', function () {
    $question = addRemediationMcq($this->questionnaire, ['Alpha', 'Beta', 'Gamma'], 1);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $row = $session->sessionQuestions()->with('options')->firstWhere('source_question_id', $question->id);

    expect($row)->not->toBeNull()
        ->and($row->type)->toBe('multiple_choice')
        ->and($row->answer_key)->toBeNull()
        ->and($row->options)->toHaveCount(3)
        ->and($row->options->pluck('position')->all())->toBe([0, 1, 2])
        ->and($row->options->firstWhere('is_correct', true)->label)->toBe('Beta')
        ->and($row->options->pluck('source_option_id')->filter()->count())->toBe(3);
});

test('editing bank option labels does not change the frozen snapshot', function () {
    $question = addRemediationMcq($this->questionnaire, ['Alpha', 'Beta', 'Gamma'], 1);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $snapshot = $session->sessionQuestions()->with('options')->first();

    $question->options()->orderBy('position')->get()->each(function (QuestionOption $option, int $index) {
        $option->update([
            'label' => 'Bank '.$index,
            'is_correct' => $index === 2,
        ]);
    });

    $snapshot->refresh();
    $snapshot->load('options');

    expect($snapshot->options->pluck('label')->all())->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and($snapshot->options->firstWhere('is_correct', true)->label)->toBe('Beta');
});

test('deleting a bank option nulls the source id but keeps the frozen option', function () {
    $question = addRemediationMcq($this->questionnaire, ['Alpha', 'Beta'], 0);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $snapshot = $session->sessionQuestions()->with('options')->first();
    $frozen = $snapshot->options->firstWhere('position', 0);

    QuestionOption::find($frozen->source_option_id)->delete();

    $frozen->refresh();

    expect($frozen->exists)->toBeTrue()
        ->and($frozen->source_option_id)->toBeNull()
        ->and($frozen->label)->toBe('Alpha')
        ->and($frozen->is_correct)->toBeTrue();
});

test('syncing session options updates rows in place and appends new options', function () {
    $question = addRemediationMcq($this->questionnaire, ['Alpha', 'Beta'], 0);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $snapshot = $session->sessionQuestions()->with('options')->first();
    $originalId = $snapshot->options->firstWhere('position', 0)->id;

    $question->options()->orderBy('position')->first()->update(['label' => 'Alpha edited']);
    $question->options()->create(['position' => 2, 'label' => 'Gamma', 'is_correct' => false]);

    app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $options = $session->fresh()->sessionQuestions()->first()->options()->get();

    expect($options)->toHaveCount(3)
        ->and($options->firstWhere('position', 0)->id)->toBe($originalId)
        ->and($options->firstWhere('position', 0)->label)->toBe('Alpha edited')
        ->and($options->firstWhere('position', 2)->label)->toBe('Gamma');
});

test('the session page exposes frozen mcq options', function () {
    addRemediationMcq($this->questionnaire, ['Alpha', 'Beta', 'Gamma'], 2);

    $session = app(ExerciseSessionService::class)
        ->createFor($this->assessment, $this->learners[0], [$this->questionnaire->id], $this->admin->id);

    $this->actingAs($this->admin)->get(route('exercise-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExerciseSessions/Show')
            ->has('sessionQuestions', 1)
            ->where('sessionQuestions.0.type', 'multiple_choice')
            ->has('sessionQuestions.0.options', 3)
            ->where('sessionQuestions.0.options.2.is_correct', true)
        );
});
