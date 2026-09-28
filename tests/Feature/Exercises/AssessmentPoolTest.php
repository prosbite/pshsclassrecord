<?php

use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\Quarter;
use App\Models\Questionnaire;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Topic;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

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

    $this->topics = Topic::factory()->count(2)->create();
    $this->questionnaires = collect([
        Questionnaire::factory()->create(['topic_id' => $this->topics[0]->id, 'position' => 0]),
        Questionnaire::factory()->create(['topic_id' => $this->topics[1]->id, 'position' => 1]),
    ]);
});

function makePoolAssessment($test, array $overrides = []): Assessment
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

test('an assessment stores selected topics and questionnaires in order', function () {
    [$first, $second] = [$this->questionnaires[0], $this->questionnaires[1]];

    $this->actingAs($this->admin)->post(route('assessments.store'), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'topic_ids' => [$this->topics[0]->id, $this->topics[1]->id],
        'questionnaire_ids' => [$second->id, $first->id],
    ])->assertRedirect(route('assessments.index'));

    $assessment = Assessment::first();

    expect($assessment->topics)->toHaveCount(2)
        ->and($assessment->questionnaires()->pluck('questionnaires.id')->all())
        ->toBe([$second->id, $first->id]);

    expect((int) $assessment->questionnaires()->whereKey($second->id)->first()->pivot->position)->toBe(0)
        ->and((int) $assessment->questionnaires()->whereKey($first->id)->first()->pivot->position)->toBe(1);
});

test('the assessment questionnaire pool can be replaced on update', function () {
    [$first, $second] = [$this->questionnaires[0], $this->questionnaires[1]];

    $assessment = makePoolAssessment($this);
    $assessment->topics()->sync([$this->topics[0]->id, $this->topics[1]->id]);
    $assessment->questionnaires()->sync([
        $first->id => ['position' => 0],
        $second->id => ['position' => 1],
    ]);

    $this->actingAs($this->admin)->put(route('assessments.update', $assessment), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'topic_ids' => [$this->topics[0]->id],
        'questionnaire_ids' => [$second->id],
    ])->assertRedirect(route('assessments.index'));

    $assessment->refresh();

    expect($assessment->topics)->toHaveCount(1)
        ->and($assessment->questionnaires()->pluck('questionnaires.id')->all())->toBe([$second->id]);
});

test('the assessment edit page exposes the selected pool', function () {
    $assessment = makePoolAssessment($this);
    $assessment->topics()->sync([$this->topics[0]->id]);
    $assessment->questionnaires()->sync([$this->questionnaires[0]->id => ['position' => 0]]);

    $this->actingAs($this->admin)->get(route('assessments.edit', $assessment))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assessments/Edit')
            ->where('selectedTopicIds', [$this->topics[0]->id])
            ->where('selectedQuestionnaireIds', [$this->questionnaires[0]->id])
        );
});

test('the assessment create page exposes the bank options', function () {
    $this->actingAs($this->admin)->get(route('assessments.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Assessments/Create')
            ->has('topics', 2)
            ->has('questionnaires', 2)
        );
});

test('an unknown questionnaire id is rejected', function () {
    $this->actingAs($this->admin)->post(route('assessments.store'), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'questionnaire_ids' => [999999],
    ])->assertSessionHasErrors('questionnaire_ids.0');

    expect(Assessment::count())->toBe(0);
});

test('an unknown topic id is rejected', function () {
    $this->actingAs($this->admin)->post(route('assessments.store'), [
        'assessment_type_id' => $this->types['long_test']->id,
        'school_year_id' => $this->schoolYear->id,
        'quarter_id' => $this->quarters[1]->id,
        'section_id' => $this->section->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
        'topic_ids' => [999999],
    ])->assertSessionHasErrors('topic_ids.0');

    expect(Assessment::count())->toBe(0);
});
