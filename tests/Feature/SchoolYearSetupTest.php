<?php

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\Quarter;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function syAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    return $admin;
}

beforeEach(function () {
    $this->admin = syAdmin();

    $this->schoolYear = SchoolYear::factory()->create([
        'year_start' => '2025',
        'year_end' => '2026',
        'status' => 'active',
    ]);

    $this->quarter = Quarter::factory()->number(1)->create([
        'school_year_id' => $this->schoolYear->id,
        'status' => 'active',
        'start_date' => '2025-07-01',
        'end_date' => '2025-09-30',
    ]);
});

test('the settings page exposes school years and the current quarter', function () {
    $this->actingAs($this->admin)->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('passingThreshold', fn ($value) => (float) $value === 75.0)
            ->has('schoolYears', 1)
            ->where('schoolYears.0.year_start', '2025')
            ->where('currentSchoolYearId', $this->schoolYear->id)
            ->where('currentQuarterId', $this->quarter->id)
        );
});

test('creating a school year provisions its four quarters', function () {
    $this->actingAs($this->admin)->post(route('settings.school-years.store'), [
        'year_start' => 2026,
        'year_end' => 2027,
    ])->assertRedirect(route('settings.edit'));

    $schoolYear = SchoolYear::where('year_start', '2026')->where('year_end', '2027')->first();

    expect($schoolYear)->not->toBeNull()
        ->and($schoolYear->status)->toBe('inactive')
        ->and($schoolYear->quarters()->count())->toBe(4)
        ->and($schoolYear->quarters()->orderBy('quarter')->pluck('quarter')->all())->toBe([1, 2, 3, 4]);
});

test('the first school year becomes current with its first quarter', function () {
    SchoolYear::query()->update(['status' => 'inactive']);
    Quarter::query()->update(['status' => 'inactive']);

    $this->actingAs($this->admin)->post(route('settings.school-years.store'), [
        'year_start' => 2030,
        'year_end' => 2031,
    ])->assertRedirect(route('settings.edit'));

    $schoolYear = SchoolYear::where('year_start', '2030')->first();

    expect($schoolYear->status)->toBe('active')
        ->and($schoolYear->quarters()->where('status', 'active')->value('quarter'))->toBe(1);
});

test('activating a school year makes it current and ensures a current quarter', function () {
    $otherYear = SchoolYear::factory()->create([
        'year_start' => '2026',
        'year_end' => '2027',
        'status' => 'inactive',
    ]);

    $otherQuarter = Quarter::factory()->number(2)->create([
        'school_year_id' => $otherYear->id,
        'status' => 'inactive',
    ]);

    $this->actingAs($this->admin)
        ->put(route('settings.school-years.activate', $otherYear))
        ->assertRedirect(route('settings.edit'));

    expect($otherYear->fresh()->status)->toBe('active')
        ->and($this->schoolYear->fresh()->status)->toBe('inactive')
        ->and($otherQuarter->fresh()->status)->toBe('active');
});

test('activating a quarter changes the current quarter', function () {
    $secondQuarter = Quarter::factory()->number(2)->create([
        'school_year_id' => $this->schoolYear->id,
        'status' => 'inactive',
    ]);

    $this->actingAs($this->admin)
        ->put(route('settings.quarters.activate', $secondQuarter))
        ->assertRedirect(route('settings.edit'));

    expect($secondQuarter->fresh()->status)->toBe('active')
        ->and($this->quarter->fresh()->status)->toBe('inactive');
});

test('a quarter from another school year cannot become current', function () {
    $otherYear = SchoolYear::factory()->create(['status' => 'inactive']);
    $otherQuarter = Quarter::factory()->number(1)->create([
        'school_year_id' => $otherYear->id,
        'status' => 'inactive',
    ]);

    $this->actingAs($this->admin)
        ->put(route('settings.quarters.activate', $otherQuarter))
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('error');

    expect($otherQuarter->fresh()->status)->toBe('inactive');
});

test('non-admins cannot manage the school year setup', function () {
    $student = User::factory()->create();
    $student->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $this->actingAs($student)->post(route('settings.school-years.store'), [
        'year_start' => 2026,
        'year_end' => 2027,
    ])->assertForbidden();

    $this->actingAs($student)
        ->put(route('settings.school-years.activate', $this->schoolYear))
        ->assertForbidden();

    $this->actingAs($student)
        ->put(route('settings.quarters.activate', $this->quarter))
        ->assertForbidden();
});

test('the assessment create page defaults to the current quarter', function () {
    $this->actingAs($this->admin)->get(route('assessments.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Create')
            ->where('selectedQuarterId', $this->quarter->id)
        );
});

test('the assessment summary defaults to the current quarter', function () {
    $secondQuarter = Quarter::factory()->number(2)->create([
        'school_year_id' => $this->schoolYear->id,
        'status' => 'active',
        'start_date' => '2025-10-01',
        'end_date' => '2025-12-31',
    ]);

    $this->quarter->forceFill(['status' => 'inactive'])->save();

    $this->actingAs($this->admin)->get(route('assessments.summary'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Assessments/Summary')
            ->where('selectedQuarterId', $secondQuarter->id)
        );
});

test('the quarterly upload page defaults to the current quarter', function () {
    $this->actingAs($this->admin)->get(route('quarterly-assessments.upload'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('QuarterlyAssessments/Upload')
            ->where('selectedQuarterId', $this->quarter->id)
        );
});

test('the student dashboard exposes the current quarter', function () {
    $gradeLevel = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);

    $student = User::factory()->create();
    $student->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $learner = Learner::factory()->create(['user_id' => $student->id]);

    Enrollment::factory()->create([
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $this->schoolYear->id,
        'status' => 'active',
    ]);

    $this->actingAs($student)->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Dashboard')
            ->where('currentQuarter', $this->quarter->quarter)
        );
});
