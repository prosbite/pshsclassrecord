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
use App\Models\Topic;
use App\Models\User;
use App\Services\ExerciseSessionService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $this->gradeLevel = GradeLevel::factory()->create();
    $this->section = Section::factory()->create(['grade_level_id' => $this->gradeLevel->id]);

    $this->quarter = Quarter::factory()->create([
        'quarter' => 1,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->type = AssessmentType::factory()->longTest()->create();

    $this->admin = User::factory()->create();
    $this->admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    $this->studentA = srStudentUser();
    $this->studentB = srStudentUser();

    $this->learnerA = Learner::factory()->create(['user_id' => $this->studentA->id]);
    $this->learnerB = Learner::factory()->create(['user_id' => $this->studentB->id]);

    foreach ([$this->learnerA, $this->learnerB] as $learner) {
        Enrollment::factory()->create([
            'learner_id' => $learner->id,
            'section_id' => $this->section->id,
            'school_year_id' => $this->schoolYear->id,
            'status' => 'active',
        ]);
    }

    $this->topic = Topic::factory()->create();
    $this->questionnaire = Questionnaire::factory()->create(['topic_id' => $this->topic->id]);

    $this->assessment = srAssessment($this);
});

function srStudentUser(): User
{
    $user = User::factory()->create();
    $user->forceFill(['role' => 'student', 'status' => 'active'])->save();

    return $user;
}

function srAssessment($test, array $overrides = []): Assessment
{
    return Assessment::factory()->create(array_merge([
        'assessment_type_id' => $test->type->id,
        'school_year_id' => $test->schoolYear->id,
        'quarter_id' => $test->quarter->id,
        'section_id' => $test->section->id,
        'user_id' => $test->admin->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
    ], $overrides));
}

function srAddText(Questionnaire $questionnaire, array $overrides = []): Question
{
    $question = Question::factory()->create(array_merge([
        'topic_id' => $questionnaire->topic_id,
        'type' => Question::TYPE_TEXT,
        'answer_key' => null,
    ], $overrides));

    $questionnaire->questions()->attach($question->id, [
        'position' => $questionnaire->questions()->count(),
    ]);

    return $question;
}

function srAddMcq(Questionnaire $questionnaire, array $labels = ['A', 'B', 'C'], int $correctIndex = 0, int $points = 1): Question
{
    $question = Question::factory()->create([
        'topic_id' => $questionnaire->topic_id,
        'type' => Question::TYPE_MULTIPLE_CHOICE,
        'points' => $points,
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

function srSession($test, Learner $learner, ?Assessment $assessment = null, ?array $questionnaireIds = null): ExerciseSession
{
    return app(ExerciseSessionService::class)->createFor(
        $assessment ?? $test->assessment,
        $learner,
        $questionnaireIds ?? [$test->questionnaire->id],
        $test->admin->id,
    );
}

test('non-students are forbidden from every student remediation route', function () {
    $session = srSession($this, $this->learnerA);
    $plain = User::factory()->create();

    $this->actingAs($this->admin)->get(route('student.remediation.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('student.remediation.show', $session))->assertForbidden();
    $this->actingAs($plain)->get(route('student.remediation.index'))->assertForbidden();
    $this->actingAs($plain)->put(route('student.remediation.submit', $session), ['answers' => []])->assertForbidden();
});

test('a student without a learner profile sees an empty remediation index', function () {
    $user = srStudentUser();

    $this->actingAs($user)->get(route('student.remediation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Remediation/Index')
            ->has('ongoing', 0)
            ->has('completed', 0)
        );
});

test('a student cannot view or submit another student session', function () {
    $session = srSession($this, $this->learnerB);

    $this->actingAs($this->studentA)->get(route('student.remediation.show', $session))->assertForbidden();
    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [],
    ])->assertForbidden();
});

test('the remediation index splits ongoing and completed sessions with answered counts', function () {
    srAddText($this->questionnaire, ['points' => 2]);
    $secondAssessment = srAssessment($this, ['title' => 'Second', 'assessment_date' => '2026-07-20']);

    $ongoing = srSession($this, $this->learnerA);
    $completed = srSession($this, $this->learnerA, $secondAssessment);

    $ongoing->sessionQuestions()->first()->update(['response_text' => 'draft answer']);
    $completed->update(['status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($this->studentA)->get(route('student.remediation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Remediation/Index')
            ->where('ongoing', function ($rows) use ($ongoing) {
                return count($rows) === 1
                    && $rows[0]['id'] === $ongoing->id
                    && $rows[0]['status'] === 'assigned'
                    && $rows[0]['answered_count'] === 1
                    && $rows[0]['total_questions'] === 1
                    && $rows[0]['percent'] === null;
            })
            ->where('completed', function ($rows) use ($completed) {
                return count($rows) === 1
                    && $rows[0]['id'] === $completed->id
                    && $rows[0]['status'] === 'completed';
            })
        );
});

test('the student show page exposes saved answers but hides keys and marks before completion', function () {
    $mcq = srAddMcq($this->questionnaire, ['Alpha', 'Beta', 'Gamma'], 1, 2);
    $text = srAddText($this->questionnaire, ['prompt_text' => 'Explain your reasoning']);

    $session = srSession($this, $this->learnerA);

    $mcqRow = $session->sessionQuestions()->with('options')->firstWhere('source_question_id', $mcq->id);
    $textRow = $session->sessionQuestions()->firstWhere('source_question_id', $text->id);
    $chosen = $mcqRow->options->firstWhere('position', 0);

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $mcqRow->id, 'selected_option_id' => $chosen->id, 'response_text' => null],
            ['session_question_id' => $textRow->id, 'selected_option_id' => null, 'response_text' => 'Because...'],
        ],
    ])->assertRedirect(route('student.remediation.index'));

    $this->actingAs($this->studentA)->get(route('student.remediation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Remediation/Show')
            ->where('session.percent', null)
            ->where('sessionQuestions', function ($questions) use ($chosen) {
                $mcq = collect($questions)->firstWhere('type', Question::TYPE_MULTIPLE_CHOICE);
                $text = collect($questions)->firstWhere('type', Question::TYPE_TEXT);

                return $mcq['selected_option_id'] === $chosen->id
                    && $text['response_text'] === 'Because...'
                    && ! array_key_exists('score', $mcq)
                    && ! array_key_exists('points', $mcq)
                    && ! array_key_exists('answer_key', $mcq)
                    && ! array_key_exists('is_correct', $mcq['options'][0])
                    && ! array_key_exists('source_option_id', $mcq['options'][0]);
            })
        );
});

test('submitting stores answers and auto-scores multiple choice only', function () {
    $right = srAddMcq($this->questionnaire, ['R0', 'R1'], 0, 2);
    $wrong = srAddMcq($this->questionnaire, ['W0', 'W1'], 0, 3);
    $blank = srAddMcq($this->questionnaire, ['B0', 'B1'], 0, 4);
    $text = srAddText($this->questionnaire, ['points' => 5]);

    $session = srSession($this, $this->learnerA);
    $rows = $session->sessionQuestions()->with('options')->get()->keyBy('source_question_id');

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $rows[$right->id]->id, 'selected_option_id' => $rows[$right->id]->options->firstWhere('is_correct', true)->id],
            ['session_question_id' => $rows[$wrong->id]->id, 'selected_option_id' => $rows[$wrong->id]->options->firstWhere('is_correct', false)->id],
            ['session_question_id' => $rows[$text->id]->id, 'response_text' => 'My free-text answer'],
        ],
    ])->assertRedirect(route('student.remediation.index'));

    $rightRow = $rows[$right->id]->fresh();
    $wrongRow = $rows[$wrong->id]->fresh();
    $blankRow = $rows[$blank->id]->fresh();
    $textRow = $rows[$text->id]->fresh();

    expect((float) $rightRow->score)->toBe(2.0)
        ->and($rightRow->graded_at)->not->toBeNull()
        ->and((float) $wrongRow->score)->toBe(0.0)
        ->and($wrongRow->graded_at)->not->toBeNull()
        ->and($blankRow->score)->toBeNull()
        ->and($blankRow->graded_at)->toBeNull()
        ->and($blankRow->selected_option_id)->toBeNull()
        ->and($textRow->response_text)->toBe('My free-text answer')
        ->and($textRow->score)->toBeNull()
        ->and($textRow->graded_at)->toBeNull();

    $session->refresh();

    expect($session->status)->toBe('submitted')
        ->and($session->submitted_at)->not->toBeNull();
});

test('a submitted session is locked against further edits', function () {
    srAddText($this->questionnaire);
    $session = srSession($this, $this->learnerA);
    $row = $session->sessionQuestions()->first();

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [['session_question_id' => $row->id, 'response_text' => 'first answer']],
    ])->assertRedirect(route('student.remediation.index'));

    expect($row->fresh()->response_text)->toBe('first answer');

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [['session_question_id' => $row->id, 'response_text' => 'edited answer']],
    ])->assertForbidden();

    expect($row->fresh()->response_text)->toBe('first answer')
        ->and($session->fresh()->status)->toBe('submitted');
});

test('a completed session rejects further resubmission', function () {
    srAddText($this->questionnaire);
    $session = srSession($this, $this->learnerA);
    $session->update(['status' => 'completed', 'completed_at' => now()]);

    $row = $session->sessionQuestions()->first();

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [['session_question_id' => $row->id, 'response_text' => 'too late']],
    ])->assertForbidden();

    expect($row->fresh()->response_text)->toBeNull();
});

test('completion reveals marks and percent but never correctness', function () {
    $mcq = srAddMcq($this->questionnaire, ['Alpha', 'Beta'], 0, 2);
    $text = srAddText($this->questionnaire, ['points' => 3]);

    $session = srSession($this, $this->learnerA);
    $mcqRow = $session->sessionQuestions()->with('options')->firstWhere('source_question_id', $mcq->id);
    $textRow = $session->sessionQuestions()->firstWhere('source_question_id', $text->id);

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $mcqRow->id, 'selected_option_id' => $mcqRow->options->firstWhere('is_correct', true)->id],
            ['session_question_id' => $textRow->id, 'response_text' => 'done'],
        ],
    ]);

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'scores' => [
            ['session_question_id' => $mcqRow->id, 'score' => 2],
            ['session_question_id' => $textRow->id, 'score' => 3],
        ],
        'status' => 'completed',
    ])->assertRedirect(route('exercise-sessions.show', $session));

    $this->actingAs($this->studentA)->get(route('student.remediation.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.status', 'completed')
            ->where('session.total_score', fn ($value) => (float) $value === 5.0)
            ->where('session.max_score', fn ($value) => (float) $value === 5.0)
            ->where('session.percent', fn ($value) => (float) $value === 100.0)
            ->where('sessionQuestions', function ($questions) {
                $mcq = collect($questions)->firstWhere('type', Question::TYPE_MULTIPLE_CHOICE);

                return (float) $mcq['score'] === 2.0
                    && $mcq['points'] === 2
                    && ! array_key_exists('answer_key', $mcq)
                    && ! array_key_exists('is_correct', $mcq['options'][0]);
            })
        );
});

test('answers must target questions and options that belong to the session', function () {
    $mcq = srAddMcq($this->questionnaire, ['A', 'B'], 0);

    $session = srSession($this, $this->learnerA);
    $otherAssessment = srAssessment($this, ['title' => 'Other', 'assessment_date' => '2026-08-01']);
    $otherSession = srSession($this, $this->learnerA, $otherAssessment);

    $mcqRow = $session->sessionQuestions()->with('options')->firstWhere('source_question_id', $mcq->id);
    $foreignRow = $otherSession->sessionQuestions()->with('options')->firstWhere('source_question_id', $mcq->id);

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $foreignRow->id, 'response_text' => 'x'],
        ],
    ])->assertSessionHasErrors('answers.0.session_question_id');

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $mcqRow->id, 'selected_option_id' => $foreignRow->options->first()->id],
        ],
    ])->assertSessionHasErrors('answers.0.selected_option_id');
});

test('the student dashboard surfaces remediation status per assessment', function () {
    srAddText($this->questionnaire);
    $session = srSession($this, $this->learnerA);
    $session->update(['status' => 'submitted', 'submitted_at' => now()]);

    $this->actingAs($this->studentA)->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Dashboard')
            ->where("remediationByAssessment.{$this->assessment->id}.id", $session->id)
            ->where("remediationByAssessment.{$this->assessment->id}.status", 'submitted')
            ->where('remediationSummary.ongoing', 1)
            ->where('remediationSummary.completed', 0)
        );
});

test('all preventive exercise sessions for an assessment can be deleted at once', function () {
    srAddText($this->questionnaire);

    $first = srSession($this, $this->learnerA);
    $second = srSession($this, $this->learnerB);

    $this->actingAs($this->studentA)
        ->delete(route('assessments.remediation.sessions.destroy', $this->assessment))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('assessments.remediation.sessions.destroy', $this->assessment))
        ->assertRedirect(route('assessments.remediation', $this->assessment));

    expect(ExerciseSession::where('assessment_id', $this->assessment->id)->count())->toBe(0);
    $this->assertDatabaseMissing('exercise_session_questions', ['exercise_session_id' => $first->id]);
    $this->assertDatabaseMissing('exercise_session_questions', ['exercise_session_id' => $second->id]);
});

test('the admin session page surfaces submitted answers and teacher overrides persist', function () {
    $mcq = srAddMcq($this->questionnaire, ['Alpha', 'Beta'], 0, 2);
    srAddText($this->questionnaire);

    $session = srSession($this, $this->learnerA);
    $mcqRow = $session->sessionQuestions()->with('options')->firstWhere('source_question_id', $mcq->id);
    $correctOption = $mcqRow->options->firstWhere('is_correct', true);

    $this->actingAs($this->studentA)->put(route('student.remediation.submit', $session), [
        'answers' => [
            ['session_question_id' => $mcqRow->id, 'selected_option_id' => $correctOption->id],
        ],
    ])->assertRedirect(route('student.remediation.index'));

    $this->actingAs($this->admin)->get(route('exercise-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ExerciseSessions/Show')
            ->where('session.status', 'submitted')
            ->where('session.answered_count', 1)
            ->where('sessionQuestions.0.selected_option_id', $correctOption->id)
        );

    $this->actingAs($this->admin)->put(route('exercise-sessions.update', $session), [
        'questionnaire_ids' => [$this->questionnaire->id],
        'scores' => [
            ['session_question_id' => $mcqRow->id, 'score' => 1.5],
        ],
        'status' => 'completed',
    ])->assertRedirect(route('exercise-sessions.show', $session));

    expect((float) $mcqRow->fresh()->score)->toBe(1.5)
        ->and($session->fresh()->status)->toBe('completed');
});
