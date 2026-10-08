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

    $this->assessment = epAssessment($this);
});

function epAssessment($test, array $overrides = []): Assessment
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

function epSection($test, string $name, array $overrides = []): Section
{
    return Section::factory()->create(array_merge([
        'grade_level_id' => $test->gradeLevel->id,
        'section_name' => $name,
    ], $overrides));
}

function epAddText(Questionnaire $questionnaire, array $overrides = []): Question
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

function epSession($test, Learner $learner, ?Assessment $assessment = null, string $kind = 'preventive'): ExerciseSession
{
    return app(ExerciseSessionService::class)->createFor(
        $assessment ?? $test->assessment,
        $learner,
        [$test->questionnaire->id],
        $test->admin->id,
        $kind,
    );
}

test('admins can open the exercises index', function () {
    $this->actingAs($this->admin)->get(route('exercises.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exercises/Index')
            ->has('sections')
            ->where('selectedSectionId', $this->section->id)
            ->where('selectedAssessmentId', $this->assessment->id)
            ->where('kind', 'preventive')
            ->has('sessions')
        );
});

test('non-admins are forbidden from the exercise pages', function () {
    $this->actingAs($this->student)->get(route('exercises.index'))->assertForbidden();
    $this->actingAs($this->student)->get(route('exercises.create'))->assertForbidden();
    $this->actingAs($this->student)->get(route('exercises.students.show', $this->learners[0]))->assertForbidden();
});

test('the kind filter only returns sessions of the chosen kind', function () {
    epAddText($this->questionnaire);
    epSession($this, $this->learners[0], null, 'preventive');
    epSession($this, $this->learners[1], null, 'enhancement');

    $this->actingAs($this->admin)->get(route('exercises.index', [
        'section' => $this->section->id,
        'assessment' => $this->assessment->id,
        'kind' => 'enhancement',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kind', 'enhancement')
            ->has('sessions', 1)
            ->where('sessions.0.kind', 'enhancement')
            ->where('sessions.0.learner.id', $this->learners[1]->id)
        );
});

test('an assessment that does not belong to the section falls back to the section default', function () {
    $otherSection = epSection($this, 'Beta');
    $otherAssessment = epAssessment($this, [
        'section_id' => $otherSection->id,
        'title' => 'Other Assessment',
    ]);

    epAssessment($this, ['title' => 'Second Assessment', 'assessment_date' => '2026-08-01']);

    $this->actingAs($this->admin)->get(route('exercises.index', [
        'section' => $this->section->id,
        'assessment' => $otherAssessment->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedSectionId', $this->section->id)
            ->where('selectedAssessmentId', $this->assessment->id)
        );
});

test('the create page includes learners, threshold and questionnaires for the selected assessment', function () {
    epAddText($this->questionnaire);
    $this->assessment->questionnaires()->attach($this->questionnaire->id, ['position' => 0]);

    $this->actingAs($this->admin)->get(route('exercises.create', [
        'section' => $this->section->id,
        'assessment' => $this->assessment->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exercises/Create')
            ->where('assessment.id', $this->assessment->id)
            ->where('threshold', 75)
            ->has('failingLearners', 2)
            ->has('poolQuestionnaires', 1)
            ->has('bankQuestionnaires', 1)
        );
});

test('creating sessions redirects back to the list with the chosen filters', function () {
    epAddText($this->questionnaire);

    $this->actingAs($this->admin)->post(route('exercises.sessions.store', $this->assessment), [
        'learner_ids' => [$this->learners[0]->id],
        'questionnaire_ids' => [$this->questionnaire->id],
    ])->assertRedirect(route('exercises.index', [
        'section' => $this->section->id,
        'assessment' => $this->assessment->id,
        'kind' => 'preventive',
    ]));

    expect(ExerciseSession::where('assessment_id', $this->assessment->id)
        ->where('learner_id', $this->learners[0]->id)
        ->exists())->toBeTrue();
});

test('the student page lists sessions across all assessments', function () {
    epAddText($this->questionnaire);
    $secondAssessment = epAssessment($this, ['title' => 'Second Assessment', 'assessment_date' => '2026-08-01']);

    epSession($this, $this->learners[0]);
    epSession($this, $this->learners[0], $secondAssessment);

    $this->actingAs($this->admin)->get(route('exercises.students.show', $this->learners[0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Exercises/Student')
            ->where('learner.id', $this->learners[0]->id)
            ->has('sessions', 2)
        );
});

test('a single session can be deleted from the exercises list', function () {
    epAddText($this->questionnaire);
    $session = epSession($this, $this->learners[0]);

    $this->actingAs($this->admin)
        ->delete(route('exercises.sessions.destroy', $session), ['kind' => 'preventive'])
        ->assertRedirect(route('exercises.index', [
            'section' => $this->section->id,
            'assessment' => $this->assessment->id,
            'kind' => 'preventive',
        ]));

    expect(ExerciseSession::find($session->id))->toBeNull();
});

test('all sessions for an assessment can be deleted from the exercises list', function () {
    epAddText($this->questionnaire);
    epSession($this, $this->learners[0]);
    epSession($this, $this->learners[1], null, 'enhancement');

    $this->actingAs($this->admin)
        ->delete(route('exercises.sessions.destroy-all', $this->assessment), ['kind' => 'preventive'])
        ->assertRedirect();

    expect(ExerciseSession::where('assessment_id', $this->assessment->id)->count())->toBe(0);
});
