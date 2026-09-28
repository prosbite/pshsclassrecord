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

function dashboardAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    return $admin;
}

test('the admin dashboard renders monitoring data', function () {
    $this->actingAs(dashboardAdmin())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary')
            ->has('counts')
            ->has('remediation')
            ->has('recentAssessments')
            ->has('recentLogins')
            ->where('summary.active_learners', 0)
            ->where('remediation.submitted', 0)
        );
});

test('the dashboard counts active learners and learners below the passing threshold', function () {
    $schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $gradeLevel = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);
    $quarter = Quarter::factory()->number(1)->create(['school_year_id' => $schoolYear->id]);
    $admin = dashboardAdmin();

    $learner = Learner::factory()->create();
    Enrollment::factory()->create([
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    $assessment = Assessment::factory()->create([
        'assessment_type_id' => AssessmentType::factory()->longTest()->create()->id,
        'school_year_id' => $schoolYear->id,
        'quarter_id' => $quarter->id,
        'section_id' => $section->id,
        'user_id' => $admin->id,
        'assessment_date' => '2026-07-15',
        'perfect_score' => 100,
    ]);

    $assessment->learners()->sync([$learner->id => ['score' => 40, 'tentative' => false]]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.active_learners', 1)
            ->where('recentAssessments.0.failing_count', 1)
            ->where('recentAssessments.0.learners_count', 1)
        );
});

test('non-admins cannot view the admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});
