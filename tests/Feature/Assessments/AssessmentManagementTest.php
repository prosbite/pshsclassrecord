<?php

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
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
});

function makeAssessment($test, array $overrides = []): Assessment
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

test('non admins cannot access assessment management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('assessments.create'))->assertForbidden();
    $this->actingAs($user)->get(route('assessments.summary'))->assertForbidden();
    $this->actingAs($user)->post(route('assessments.store'), [])->assertForbidden();
});

test('an assessment with learner scores can be created', function () {
    $response = $this->actingAs($this->admin)->post(route('assessments.store'), [
        'title' => 'Quarter 1 Long Test',
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 50,
        'learner_scores' => [
            ['learner_id' => $this->learners[0]->id, 'score' => 45],
            ['learner_id' => $this->learners[1]->id, 'score' => 40],
        ],
    ]);

    $response->assertRedirect(route('assessments.index'));

    $assessment = Assessment::first();

    expect($assessment)->not->toBeNull()
        ->and($assessment->title)->toBe('Quarter 1 Long Test')
        ->and($assessment->user_id)->toBe($this->admin->id)
        ->and($assessment->perfect_score)->toBe(50)
        ->and($assessment->learners)->toHaveCount(2);

    expect((float) $assessment->learners()->whereKey($this->learners[0]->id)->first()->pivot->score)->toBe(45.0);
});

test('an assessment stores the tentative flag on learner pivots', function () {
    $this->actingAs($this->admin)->post(route('assessments.store'), [
        'title' => 'Tentative Long Test',
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'learner_scores' => [
            ['learner_id' => $this->learners[0]->id, 'score' => 45, 'tentative' => true],
            ['learner_id' => $this->learners[1]->id, 'score' => 40, 'tentative' => false],
        ],
    ])->assertRedirect(route('assessments.index'));

    $assessment = Assessment::first();

    expect((bool) $assessment->learners()->whereKey($this->learners[0]->id)->first()->pivot->tentative)->toBeTrue()
        ->and((bool) $assessment->learners()->whereKey($this->learners[1]->id)->first()->pivot->tentative)->toBeFalse();
});

test('the tentative flag is validated as a boolean', function () {
    $response = $this->actingAs($this->admin)->post(route('assessments.store'), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'learner_scores' => [
            ['learner_id' => $this->learners[0]->id, 'score' => 45, 'tentative' => 'not-a-boolean'],
        ],
    ]);

    $response->assertSessionHasErrors('learner_scores.0.tentative');
    expect(Assessment::count())->toBe(0);
});

test('store rejects learner scores for unknown learners', function () {
    $response = $this->actingAs($this->admin)->post(route('assessments.store'), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'learner_scores' => [
            ['learner_id' => 999999, 'score' => 5],
        ],
    ]);

    $response->assertSessionHasErrors('learner_scores.0.learner_id');
    expect(Assessment::count())->toBe(0);
});

test('assessment metadata and learner scores can be updated', function () {
    $assessment = makeAssessment($this, ['assessment_type_id' => $this->types['formative']->id]);

    $assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 10],
        $this->learners[1]->id => ['score' => 20],
    ]);

    $response = $this->actingAs($this->admin)->put(route('assessments.update', $assessment), [
        'title' => 'Updated title',
        'assessment_type_id' => $this->types['formative']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-08-01',
        'perfect_score' => 25,
        'learner_scores' => [
            ['learner_id' => $this->learners[0]->id, 'score' => 25],
        ],
    ]);

    $response->assertRedirect(route('assessments.index'));

    $assessment->refresh();

    expect($assessment->title)->toBe('Updated title')
        ->and($assessment->perfect_score)->toBe(25)
        ->and($assessment->assessment_date->toDateString())->toBe('2026-08-01')
        ->and($assessment->learners)->toHaveCount(2);

    $updatedScore = fn (int $learnerId) => (float) $assessment->learners
        ->firstWhere('id', $learnerId)->pivot->score;

    expect($updatedScore($this->learners[0]->id))->toBe(25.0)
        ->and($updatedScore($this->learners[1]->id))->toBe(20.0);
});

test('updating with an empty learner payload leaves existing scores intact', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 10],
        $this->learners[1]->id => ['score' => 20],
    ]);

    $this->actingAs($this->admin)->put(route('assessments.update', $assessment), [
        'title' => 'Metadata only',
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'learner_scores' => [],
    ])->assertRedirect(route('assessments.index'));

    expect($assessment->fresh()->learners)->toHaveCount(2);
});

test('updating learner scores persists the tentative flag', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 10, 'tentative' => false],
        $this->learners[1]->id => ['score' => 20, 'tentative' => false],
    ]);

    $this->actingAs($this->admin)->put(route('assessments.update', $assessment), [
        'title' => 'Updated title',
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'learner_scores' => [
            ['learner_id' => $this->learners[0]->id, 'score' => 10, 'tentative' => true],
        ],
    ])->assertRedirect(route('assessments.index'));

    $learners = $assessment->fresh()->learners;

    expect((bool) $learners->firstWhere('id', $this->learners[0]->id)->pivot->tentative)->toBeTrue()
        ->and((bool) $learners->firstWhere('id', $this->learners[1]->id)->pivot->tentative)->toBeFalse();
});

test('the edit page exposes the assessment and its learner scores', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([$this->learners[0]->id => ['score' => 12]]);

    $response = $this->actingAs($this->admin)->get(route('assessments.edit', $assessment));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Assessments/Edit')
        ->where('assessment.id', $assessment->id)
        ->where('assessment.assessment_type.code', 'long_test')
        ->where("learnerScores.{$this->learners[0]->id}", fn ($score) => (float) $score === 12.0)
    );
});

test('the edit page exposes learner tentatives', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 12, 'tentative' => true],
        $this->learners[1]->id => ['score' => 8, 'tentative' => false],
    ]);

    $this->actingAs($this->admin)->get(route('assessments.edit', $assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Edit')
            ->where("learnerTentatives.{$this->learners[0]->id}", true)
            ->where("learnerTentatives.{$this->learners[1]->id}", false)
        );
});

test('an assessment and its pivot rows can be deleted', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([$this->learners[0]->id => ['score' => 5]]);

    $this->actingAs($this->admin)
        ->delete(route('assessments.destroy', $assessment))
        ->assertRedirect(route('assessments.index'));

    expect(Assessment::find($assessment->id))->toBeNull();
    $this->assertDatabaseMissing('assessment_learners', ['assessment_id' => $assessment->id]);
});

test('section learners endpoint returns active enrollments for the current school year', function () {
    $inactiveLearner = Learner::factory()->create();
    Enrollment::factory()->create([
        'learner_id' => $inactiveLearner->id,
        'section_id' => $this->section->id,
        'school_year_id' => $this->schoolYear->id,
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($this->admin)->getJson(
        route('assessments.section-learners', ['section_id' => $this->section->id])
    );

    $response->assertOk()->assertJsonCount(2);
});

test('the summary page returns structured assessments for the selected section', function () {
    $assessment = makeAssessment($this);

    $response = $this->actingAs($this->admin)->get(
        route('assessments.summary', ['section' => $this->section->id])
    );

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Assessments/Summary')
        ->has('assessments', 1)
        ->where('assessments.0.id', $assessment->id)
        ->where('assessments.0.assessment_type.code', 'long_test')
        ->has('quarters', 4)
        ->where('section.id', $this->section->id)
        ->has('section.enrollments', 2)
    );
});

test('the summary page exposes the tentative flag on assessment learners', function () {
    $assessment = makeAssessment($this);
    $assessment->learners()->sync([
        $this->learners[0]->id => ['score' => 30, 'tentative' => true],
        $this->learners[1]->id => ['score' => 25, 'tentative' => false],
    ]);

    $learnerId = $this->learners[0]->id;

    $this->actingAs($this->admin)->get(route('assessments.summary', ['section' => $this->section->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Summary')
            ->has('assessments', 1)
            ->where('assessments.0.learners', fn ($learners) => (bool) (collect($learners)->firstWhere('id', $learnerId)['pivot']['tentative'] ?? false))
        );
});

test('the summary page shows an empty state when no concrete section is selected', function () {
    $response = $this->actingAs($this->admin)->get(route('assessments.summary'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Assessments/Summary')
        ->has('assessments', 0)
        ->where('section', null)
    );
});

test('the student dashboard exposes structured assessments with the student score', function () {
    $studentUser = User::factory()->create();
    $studentUser->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $learner = $this->learners[0];
    $learner->forceFill(['user_id' => $studentUser->id])->save();

    $assessment = makeAssessment($this, ['assessment_type_id' => $this->types['alternative']->id]);
    $assessment->learners()->sync([$learner->id => ['score' => 33]]);

    $response = $this->actingAs($studentUser)->get(route('student.dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Students/Dashboard')
        ->has('assessments', 1)
        ->where('assessments.0.assessmentType.code', 'alternative')
        ->where('assessments.0.assessment_date', '2026-07-15')
        ->where('assessments.0.score', fn ($score) => (float) $score === 33.0)
        ->where('assessments.0.tentative', false)
        ->where('section.id', $this->section->id)
    );
});

test('the student dashboard exposes the tentative flag', function () {
    $studentUser = User::factory()->create();
    $studentUser->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $learner = $this->learners[0];
    $learner->forceFill(['user_id' => $studentUser->id])->save();

    $assessment = makeAssessment($this, ['assessment_type_id' => $this->types['alternative']->id]);
    $assessment->learners()->sync([$learner->id => ['score' => 33, 'tentative' => true]]);

    $this->actingAs($studentUser)->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Dashboard')
            ->has('assessments', 1)
            ->where('assessments.0.tentative', true)
        );
});
