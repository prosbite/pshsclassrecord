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
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Topic;
use App\Models\User;
use App\Services\ExerciseSessionService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $this->gradeLevel = GradeLevel::factory()->create();
    $this->section = Section::factory()->create([
        'grade_level_id' => $this->gradeLevel->id,
        'section_name' => 'Alpha',
    ]);

    $this->quarter = Quarter::factory()->create([
        'quarter' => 1,
        'school_year_id' => $this->schoolYear->id,
    ]);

    $this->type = AssessmentType::factory()->longTest()->create();

    $this->admin = User::factory()->create();
    $this->admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    $this->student = User::factory()->create();
    $this->student->forceFill(['role' => 'student', 'status' => 'active'])->save();

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

    $this->assessment = esubAssessment($this);
});

function esubAssessment($test, array $overrides = []): Assessment
{
    return Assessment::factory()->create(array_merge([
        'assessment_type_id' => $test->type->id,
        'school_year_id' => $test->schoolYear->id,
        'quarter_id' => $test->quarter->id,
        'section_id' => $test->section->id,
        'user_id' => $test->admin->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'title' => 'Unit Test',
    ], $overrides));
}

function esubAddText(Questionnaire $questionnaire, array $overrides = []): Question
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

function esubSession($test, Learner $learner, ?Assessment $assessment = null, string $kind = 'preventive'): ExerciseSession
{
    return app(ExerciseSessionService::class)->createFor(
        $assessment ?? $test->assessment,
        $learner,
        [$test->questionnaire->id],
        $test->admin->id,
        $kind,
    );
}

function esubSubmit(ExerciseSession $session): ExerciseSession
{
    app(ExerciseSessionService::class)->submitAnswers($session, []);

    return $session->refresh();
}

test('admins can open the exercise submissions page', function () {
    $this->actingAs($this->admin)->get(route('exercises.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exercises/Submissions')
            ->where('tab', 'submitted')
            ->has('counts')
            ->has('sessions.data')
        );
});

test('non-admins are forbidden from the exercise submissions page', function () {
    $this->actingAs($this->student)->get(route('exercises.submissions.index'))->assertForbidden();
});

test('the tab query returns only sessions with that status', function () {
    esubAddText($this->questionnaire);

    $assigned = esubSession($this, $this->learners[0]);
    $submitted = esubSubmit(esubSession($this, $this->learners[1]));

    $this->actingAs($this->admin)->get(route('exercises.submissions.index', ['tab' => 'assigned']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'assigned')
            ->has('sessions.data', 1)
            ->where('sessions.data.0.id', $assigned->id)
        );

    $this->actingAs($this->admin)->get(route('exercises.submissions.index', ['tab' => 'submitted']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'submitted')
            ->has('sessions.data', 1)
            ->where('sessions.data.0.id', $submitted->id)
        );
});

test('search filters sessions by learner first or last name', function () {
    esubAddText($this->questionnaire);

    $this->learners[0]->forceFill(['first_name' => 'Alice', 'last_name' => 'Zephyr'])->save();
    $this->learners[1]->forceFill(['first_name' => 'Bob', 'last_name' => 'Quartz'])->save();

    $alice = esubSubmit(esubSession($this, $this->learners[0]));
    esubSubmit(esubSession($this, $this->learners[1]));

    $this->actingAs($this->admin)->get(route('exercises.submissions.index', ['search' => 'Zephyr']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('search', 'Zephyr')
            ->has('sessions.data', 1)
            ->where('sessions.data.0.id', $alice->id)
        );

    $this->actingAs($this->admin)->get(route('exercises.submissions.index', ['search' => 'Bob']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.learner.name', 'Quartz, Bob')
        );
});

test('counts reflect both statuses for the current school year', function () {
    esubAddText($this->questionnaire);

    esubSubmit(esubSession($this, $this->learners[0]));
    esubSession($this, $this->learners[1]);

    $this->actingAs($this->admin)->get(route('exercises.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.submitted', 1)
            ->where('counts.assigned', 1)
        );
});
