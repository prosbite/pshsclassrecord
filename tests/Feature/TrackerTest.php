<?php

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\LoginActivity;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SimulationActivity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Learner, 2: Section, 3: SchoolYear}
 */
function trackerStudent(): array
{
    $schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $gradeLevel = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);

    $user = User::factory()->create();
    $user->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $learner = Learner::factory()->create(['user_id' => $user->id]);

    Enrollment::factory()->create([
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    return [$user, $learner, $section, $schoolYear];
}

function trackerAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    return $admin;
}

test('starting a simulation records a simulation activity for the student', function () {
    [$user, $learner, $section, $schoolYear] = trackerStudent();

    $this->actingAs($user)->post(route('student.simulation.store'), ['quarter' => 2])
        ->assertNoContent();

    $activity = SimulationActivity::first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->learner_id)->toBe($learner->id)
        ->and($activity->section_id)->toBe($section->id)
        ->and($activity->school_year_id)->toBe($schoolYear->id)
        ->and($activity->quarter)->toBe(2);
});

test('non-students cannot record a simulation', function () {
    $this->actingAs(trackerAdmin())
        ->post(route('student.simulation.store'), ['quarter' => 1])
        ->assertForbidden();
});

test('the tracker page renders login and simulation data for admins', function () {
    [$student] = trackerStudent();

    $this->actingAs($student)->post(route('student.simulation.store'), ['quarter' => 3]);

    $this->actingAs(trackerAdmin())->get(route('tracker.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tracker/Index')
            ->has('filters')
            ->has('summary')
            ->has('topUsers')
            ->has('recentActivities')
            ->has('simulationSummary')
            ->has('topStudents')
            ->has('recentSimulations')
            ->where('simulationSummary.total_simulations', 1)
            ->where('simulationSummary.unique_students', 1)
        );
});

test('the tracker lists students who have not simulated yet', function () {
    [$user, $learner, $section, $schoolYear] = trackerStudent();

    $otherUser = User::factory()->create();
    $otherUser->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $otherLearner = Learner::factory()->create([
        'user_id' => $otherUser->id,
        'first_name' => 'Aaron',
        'last_name' => 'Zzz',
    ]);

    Enrollment::factory()->create([
        'learner_id' => $otherLearner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->post(route('student.simulation.store'), ['quarter' => 1]);

    $this->actingAs(trackerAdmin())->get(route('tracker.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('simulationSummary.unique_students', 1)
            ->where('simulationSummary.not_simulated_students', 1)
            ->has('notSimulatedStudents', 1)
            ->where('notSimulatedStudents.0.id', $otherLearner->id)
            ->where('notSimulatedStudents.0.name', 'Zzz, Aaron')
            ->where('notSimulatedStudents.0.section', $section->section_name)
        );
});

test('the tracker filters login activity by a date range', function () {
    $user = User::factory()->create();

    $inRange = LoginActivity::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
    ]);
    $inRange->forceFill(['created_at' => '2026-05-31 23:30:00'])->save();

    $outOfRange = LoginActivity::create([
        'user_id' => $user->id,
        'user_name' => $user->name,
    ]);
    $outOfRange->forceFill(['created_at' => '2026-01-05 09:00:00'])->save();

    $this->actingAs(trackerAdmin())
        ->get(route('tracker.index', ['from' => '2026-05-01', 'to' => '2026-05-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-05-01')
            ->where('filters.to', '2026-05-31')
            ->where('summary.total_logins', 1)
            ->has('recentActivities', 1)
            ->where('recentActivities.0.id', $inRange->id)
            ->has('topUsers', 1)
            ->where('topUsers.0.login_count', 1)
        );
});

test('the tracker filters simulation activity by a date range', function () {
    [$student, $learner, $section, $schoolYear] = trackerStudent();

    $inRange = SimulationActivity::create([
        'user_id' => $student->id,
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'quarter' => 2,
    ]);
    $inRange->forceFill(['created_at' => '2026-05-31 23:30:00'])->save();

    $outOfRange = SimulationActivity::create([
        'user_id' => $student->id,
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'quarter' => 1,
    ]);
    $outOfRange->forceFill(['created_at' => '2026-01-05 09:00:00'])->save();

    $this->actingAs(trackerAdmin())
        ->get(route('tracker.index', ['from' => '2026-05-01', 'to' => '2026-05-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-05-01')
            ->where('filters.to', '2026-05-31')
            ->where('simulationSummary.total_simulations', 1)
            ->has('recentSimulations', 1)
            ->where('recentSimulations.0.id', $inRange->id)
            ->has('topStudents', 1)
            ->where('topStudents.0.simulation_count', 1)
        );
});

test('the tracker ignores invalid date filters', function () {
    $this->actingAs(trackerAdmin())
        ->get(route('tracker.index', ['from' => 'not-a-date', 'to' => '2026-13-40']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', null)
            ->where('filters.to', null)
        );
});

test('non-admins cannot view the tracker page', function () {
    [$student] = trackerStudent();

    $this->actingAs($student)->get(route('tracker.index'))->assertForbidden();
});
