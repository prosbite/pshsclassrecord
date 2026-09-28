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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $this->question = addBankQuestionTo($this->questionnaire);

    $this->assessment = makeBankAssessment($this);

    $this->session = ExerciseSession::factory()->create([
        'assessment_id' => $this->assessment->id,
        'learner_id' => $this->learners[0]->id,
        'created_by' => $this->admin->id,
    ]);
});

function addBankQuestionTo(Questionnaire $questionnaire, array $overrides = []): Question
{
    $question = Question::factory()->create(array_merge([
        'topic_id' => $questionnaire->topic_id,
    ], $overrides));

    $questionnaire->questions()->attach($question->id, [
        'position' => $questionnaire->questions()->count(),
    ]);

    return $question;
}

function makeBankAssessment($test, array $overrides = []): Assessment
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

test('non admins cannot access the exercise bank routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('topics.index'))->assertForbidden();
    $this->actingAs($user)->get(route('topics.create'))->assertForbidden();
    $this->actingAs($user)->post(route('topics.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('topics.edit', $this->topic))->assertForbidden();
    $this->actingAs($user)->put(route('topics.update', $this->topic), [])->assertForbidden();
    $this->actingAs($user)->delete(route('topics.destroy', $this->topic))->assertForbidden();

    $this->actingAs($user)->get(route('questionnaires.index'))->assertForbidden();
    $this->actingAs($user)->get(route('questionnaires.create'))->assertForbidden();
    $this->actingAs($user)->post(route('questionnaires.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('questionnaires.edit', $this->questionnaire))->assertForbidden();
    $this->actingAs($user)->put(route('questionnaires.update', $this->questionnaire), [])->assertForbidden();
    $this->actingAs($user)->delete(route('questionnaires.destroy', $this->questionnaire))->assertForbidden();

    $this->actingAs($user)->get(route('questions.index'))->assertForbidden();
    $this->actingAs($user)->get(route('questions.create'))->assertForbidden();
    $this->actingAs($user)->post(route('questions.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('questions.edit', $this->question))->assertForbidden();
    $this->actingAs($user)->put(route('questions.update', $this->question), [])->assertForbidden();
    $this->actingAs($user)->delete(route('questions.destroy', $this->question))->assertForbidden();

    $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('settings.update'), [])->assertForbidden();

    $this->actingAs($user)->get(route('assessments.remediation', $this->assessment))->assertForbidden();
    $this->actingAs($user)->post(route('assessments.remediation.sessions.store', $this->assessment), [])->assertForbidden();

    $this->actingAs($user)->get(route('exercise-sessions.show', $this->session))->assertForbidden();
    $this->actingAs($user)->put(route('exercise-sessions.update', $this->session), [])->assertForbidden();
    $this->actingAs($user)->delete(route('exercise-sessions.destroy', $this->session))->assertForbidden();
});

test('a topic can be created updated and deleted', function () {
    $this->actingAs($this->admin)->post(route('topics.store'), [
        'name' => 'Fractions',
        'description' => 'Number sense',
    ])->assertRedirect(route('topics.index'));

    $topic = Topic::where('name', 'Fractions')->first();
    expect($topic)->not->toBeNull()
        ->and($topic->description)->toBe('Number sense');

    $this->actingAs($this->admin)->put(route('topics.update', $topic), [
        'name' => 'Fractions and Decimals',
        'description' => 'Updated',
    ])->assertRedirect(route('topics.index'));

    expect($topic->fresh()->name)->toBe('Fractions and Decimals');

    $this->actingAs($this->admin)
        ->delete(route('topics.destroy', $topic))
        ->assertRedirect(route('topics.index'));

    expect(Topic::find($topic->id))->toBeNull();
});

test('a topic name must be unique', function () {
    $this->actingAs($this->admin)->post(route('topics.store'), [
        'name' => $this->topic->name,
    ])->assertSessionHasErrors('name');
});

test('deleting a topic with questionnaires is blocked', function () {
    $response = $this->actingAs($this->admin)->delete(route('topics.destroy', $this->topic));

    $response->assertRedirect(route('topics.index'))->assertSessionHasErrors('topic');
    expect(Topic::find($this->topic->id))->not->toBeNull();
});

test('a questionnaire can be created updated and deleted', function () {
    $this->actingAs($this->admin)->post(route('questionnaires.store'), [
        'topic_id' => $this->topic->id,
        'title' => 'Warm-up set',
        'instructions' => 'Answer all items.',
        'position' => 1,
    ])->assertRedirect(route('questionnaires.index'));

    $questionnaire = Questionnaire::where('title', 'Warm-up set')->first();
    expect($questionnaire)->not->toBeNull()
        ->and($questionnaire->topic_id)->toBe($this->topic->id);

    $this->actingAs($this->admin)->put(route('questionnaires.update', $questionnaire), [
        'topic_id' => $this->topic->id,
        'title' => 'Warm-up set v2',
        'position' => 2,
    ])->assertRedirect(route('questionnaires.index'));

    expect($questionnaire->fresh()->title)->toBe('Warm-up set v2');

    $this->actingAs($this->admin)
        ->delete(route('questionnaires.destroy', $questionnaire))
        ->assertRedirect(route('questionnaires.index'));

    expect(Questionnaire::find($questionnaire->id))->toBeNull();
});

test('a questionnaire adds and removes its own topic questions', function () {
    $extra = addBankQuestionTo($this->questionnaire, ['prompt_text' => 'Extra item']);

    $this->actingAs($this->admin)->put(route('questionnaires.update', $this->questionnaire), [
        'topic_id' => $this->topic->id,
        'title' => $this->questionnaire->title,
        'question_ids' => [$this->question->id, $extra->id],
    ])->assertRedirect(route('questionnaires.index'));

    expect($this->questionnaire->questions()->pluck('questions.id')->all())
        ->toBe([$this->question->id, $extra->id]);

    $this->actingAs($this->admin)->put(route('questionnaires.update', $this->questionnaire), [
        'topic_id' => $this->topic->id,
        'title' => $this->questionnaire->title,
        'question_ids' => [$extra->id],
    ])->assertRedirect(route('questionnaires.index'));

    expect($this->questionnaire->questions()->pluck('questions.id')->all())
        ->toBe([$extra->id]);
});

test('a questionnaire rejects questions from another topic', function () {
    $otherTopic = Topic::factory()->create();
    $foreign = Question::factory()->create(['topic_id' => $otherTopic->id]);

    $this->actingAs($this->admin)->put(route('questionnaires.update', $this->questionnaire), [
        'topic_id' => $this->topic->id,
        'title' => $this->questionnaire->title,
        'question_ids' => [$foreign->id],
    ])->assertSessionHasErrors('question_ids.0');
});

test('a question can be reused across questionnaires', function () {
    $second = Questionnaire::factory()->create(['topic_id' => $this->topic->id]);

    $this->actingAs($this->admin)->put(route('questionnaires.update', $second), [
        'topic_id' => $this->topic->id,
        'title' => $second->title,
        'question_ids' => [$this->question->id],
    ])->assertRedirect(route('questionnaires.index'));

    expect($this->questionnaire->questions()->whereKey($this->question->id)->exists())->toBeTrue()
        ->and($second->questions()->whereKey($this->question->id)->exists())->toBeTrue();
});

test('the questionnaire index filters by topic', function () {
    $otherTopic = Topic::factory()->create();
    Questionnaire::factory()->create(['topic_id' => $otherTopic->id]);

    $this->actingAs($this->admin)
        ->get(route('questionnaires.index', ['topic' => $this->topic->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questionnaires/Index')
            ->has('questionnaires', 1)
            ->where('questionnaires.0.id', $this->questionnaire->id)
        );
});

test('a question can be created with prompt text on a topic', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'prompt_text' => 'What is 1/2 + 1/4?',
        'points' => 3,
        'answer_key' => '3/4',
    ])->assertRedirect(route('questions.index'));

    $question = Question::where('prompt_text', 'What is 1/2 + 1/4?')->first();
    expect($question)->not->toBeNull()
        ->and($question->points)->toBe(3)
        ->and($question->topic_id)->toBe($this->topic->id)
        ->and($question->questionnaires()->count())->toBe(0);
});

test('a question can be created with an uploaded image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'points' => 2,
        'image' => UploadedFile::fake()->image('question.png', 120, 120),
    ])->assertRedirect(route('questions.index'));

    $question = Question::query()->whereNotNull('image_path')->first();

    expect($question)->not->toBeNull();
    Storage::disk('public')->assertExists($question->image_path);
    expect($question->image_url)->toContain('/storage/');
});

test('a question requires a topic', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'prompt_text' => 'Orphan question',
        'points' => 1,
    ])->assertSessionHasErrors('topic_id');
});

test('a question requires prompt text or an image', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'prompt_text' => '',
        'points' => 1,
    ])->assertSessionHasErrors('image');
});

test('a question can be updated and deleted', function () {
    $this->actingAs($this->admin)->put(route('questions.update', $this->question), [
        'topic_id' => $this->topic->id,
        'prompt_text' => 'Updated prompt',
        'points' => 5,
    ])->assertRedirect(route('questions.index'));

    expect($this->question->fresh()->prompt_text)->toBe('Updated prompt')
        ->and($this->question->fresh()->points)->toBe(5);

    $this->actingAs($this->admin)
        ->delete(route('questions.destroy', $this->question))
        ->assertRedirect(route('questions.index'));

    expect(Question::find($this->question->id))->toBeNull();
});

test('the question index filters by topic', function () {
    $otherTopic = Topic::factory()->create();
    Question::factory()->create(['topic_id' => $otherTopic->id]);

    $this->actingAs($this->admin)
        ->get(route('questions.index', ['topic' => $this->topic->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Index')
            ->has('questions', 1)
            ->where('questions.0.id', $this->question->id)
        );
});

test('the bank pages render for admins', function () {
    $this->actingAs($this->admin)->get(route('topics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Topics/Index')
            ->has('topics', 1)
        );

    $this->actingAs($this->admin)->get(route('topics.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Topics/Create'));

    $this->actingAs($this->admin)->get(route('topics.edit', $this->topic))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Topics/Edit')
            ->where('topic.id', $this->topic->id)
            ->has('topic.questionnaires', 1)
        );

    $this->actingAs($this->admin)->get(route('questionnaires.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questionnaires/Index')
            ->has('questionnaires', 1)
        );

    $this->actingAs($this->admin)->get(route('questionnaires.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Questionnaires/Create'));

    $this->actingAs($this->admin)->get(route('questionnaires.edit', $this->questionnaire))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questionnaires/Edit')
            ->where('questionnaire.id', $this->questionnaire->id)
            ->has('questionnaire.questions', 1)
        );

    $this->actingAs($this->admin)->get(route('questions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Index')
            ->has('questions', 1)
        );

    $this->actingAs($this->admin)->get(route('questions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Questions/Create'));

    $this->actingAs($this->admin)->get(route('questions.edit', $this->question))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Edit')
            ->where('question.id', $this->question->id)
        );
});

test('a multiple choice question is created with its options', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'prompt_text' => 'Pick one',
        'points' => 2,
        'answer_key' => 'should be ignored',
        'options' => [
            ['label' => 'One', 'is_correct' => false],
            ['label' => 'Two', 'is_correct' => true],
            ['label' => 'Three', 'is_correct' => false],
        ],
    ])->assertRedirect(route('questions.index'));

    $question = Question::where('prompt_text', 'Pick one')->first();

    expect($question)->not->toBeNull()
        ->and($question->type)->toBe('multiple_choice')
        ->and($question->answer_key)->toBeNull();

    $options = $question->options()->get();

    expect($options)->toHaveCount(3)
        ->and($options->pluck('position')->all())->toBe([0, 1, 2])
        ->and($options->pluck('label')->all())->toBe(['One', 'Two', 'Three'])
        ->and($options->firstWhere('is_correct', true)->label)->toBe('Two');
});

test('a multiple choice question needs at least two options', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'prompt_text' => 'Too few',
        'points' => 1,
        'options' => [
            ['label' => 'Only', 'is_correct' => true],
        ],
    ])->assertSessionHasErrors('options');

    expect(Question::where('prompt_text', 'Too few')->exists())->toBeFalse();
});

test('a multiple choice question needs exactly one correct option', function () {
    $payload = [
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'prompt_text' => 'Mark one',
        'points' => 1,
    ];

    $this->actingAs($this->admin)->post(route('questions.store'), $payload + [
        'options' => [
            ['label' => 'A', 'is_correct' => false],
            ['label' => 'B', 'is_correct' => false],
        ],
    ])->assertSessionHasErrors('options');

    $this->actingAs($this->admin)->post(route('questions.store'), $payload + [
        'options' => [
            ['label' => 'A', 'is_correct' => true],
            ['label' => 'B', 'is_correct' => true],
        ],
    ])->assertSessionHasErrors('options');

    expect(Question::where('prompt_text', 'Mark one')->exists())->toBeFalse();
});

test('an unknown question type is rejected', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'type' => 'essay',
        'prompt_text' => 'Nope',
        'points' => 1,
    ])->assertSessionHasErrors('type');
});

test('omitting the type defaults to a text question', function () {
    $this->actingAs($this->admin)->post(route('questions.store'), [
        'topic_id' => $this->topic->id,
        'prompt_text' => 'Explain your reasoning',
        'points' => 2,
        'answer_key' => 'Sample',
    ])->assertRedirect(route('questions.index'));

    $question = Question::where('prompt_text', 'Explain your reasoning')->first();

    expect($question->type)->toBe('text')
        ->and($question->answer_key)->toBe('Sample');
});

test('updating a multiple choice question reorders and removes options', function () {
    $question = Question::factory()->create([
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'answer_key' => null,
    ]);

    $a = $question->options()->create(['position' => 0, 'label' => 'A', 'is_correct' => true]);
    $b = $question->options()->create(['position' => 1, 'label' => 'B', 'is_correct' => false]);
    $c = $question->options()->create(['position' => 2, 'label' => 'C', 'is_correct' => false]);

    $this->actingAs($this->admin)->put(route('questions.update', $question), [
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'prompt_text' => $question->prompt_text,
        'points' => 1,
        'options' => [
            ['id' => $c->id, 'label' => 'C edited', 'is_correct' => true],
            ['id' => $a->id, 'label' => 'A', 'is_correct' => false],
            ['label' => 'D', 'is_correct' => false],
        ],
    ])->assertRedirect(route('questions.index'));

    $options = $question->fresh()->options()->get();

    expect($options)->toHaveCount(3)
        ->and($options->pluck('id')->all()[0])->toBe($c->id)
        ->and($options->pluck('id')->all()[1])->toBe($a->id)
        ->and($options->pluck('position')->all())->toBe([0, 1, 2])
        ->and($options->pluck('label')->all())->toBe(['C edited', 'A', 'D'])
        ->and($options->firstWhere('is_correct', true)->id)->toBe($c->id);

    $this->assertDatabaseMissing('question_options', ['id' => $b->id]);
});

test('switching a question to text clears its options and keeps the answer key', function () {
    $question = Question::factory()->create([
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'answer_key' => null,
    ]);

    $question->options()->create(['position' => 0, 'label' => 'A', 'is_correct' => true]);
    $question->options()->create(['position' => 1, 'label' => 'B', 'is_correct' => false]);

    $this->actingAs($this->admin)->put(route('questions.update', $question), [
        'topic_id' => $this->topic->id,
        'type' => 'text',
        'prompt_text' => $question->prompt_text,
        'points' => 1,
        'answer_key' => 'A',
    ])->assertRedirect(route('questions.index'));

    expect($question->fresh()->options()->count())->toBe(0)
        ->and($question->fresh()->type)->toBe('text')
        ->and($question->fresh()->answer_key)->toBe('A');
});

test('the question index exposes the type and option count', function () {
    $mcq = Question::factory()->create([
        'topic_id' => $this->topic->id,
        'type' => 'multiple_choice',
        'position' => 5,
    ]);

    QuestionOption::factory()->count(2)->create(['question_id' => $mcq->id]);

    $this->actingAs($this->admin)->get(route('questions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Index')
            ->where('questions', function ($questions) use ($mcq) {
                $row = collect($questions)->firstWhere('id', $mcq->id);

                return $row !== null
                    && $row['type'] === 'multiple_choice'
                    && (int) $row['options_count'] === 2;
            })
        );
});
