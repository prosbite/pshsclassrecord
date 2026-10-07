<?php

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @return array{0: User, 1: Enrollment}
 */
function passwordResetStudent(): array
{
    $schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $gradeLevel = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);

    $user = User::factory()->create(['password' => 'original-password']);
    $user->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $learner = Learner::factory()->create(['user_id' => $user->id]);

    $enrollment = Enrollment::factory()->create([
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    return [$user, $enrollment];
}

function passwordResetAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    return $admin;
}

test('admins can reset a student password to the default', function () {
    [$student, $enrollment] = passwordResetStudent();

    $this->actingAs(passwordResetAdmin())
        ->post(route('students.reset-password', $enrollment->id))
        ->assertRedirect(route('students'));

    $student->refresh();

    expect(Hash::check('12345678', $student->password))->toBeTrue()
        ->and(Hash::check('original-password', $student->password))->toBeFalse();
});

test('non-admins cannot reset a student password', function () {
    [$student, $enrollment] = passwordResetStudent();

    $this->actingAs($student)
        ->post(route('students.reset-password', $enrollment->id))
        ->assertForbidden();

    $student->refresh();

    expect(Hash::check('12345678', $student->password))->toBeFalse();
});

test('resetting a password without an account flashes an error', function () {
    $schoolYear = SchoolYear::factory()->create(['status' => 'active']);
    $gradeLevel = GradeLevel::factory()->create();
    $section = Section::factory()->create(['grade_level_id' => $gradeLevel->id]);
    $learner = Learner::factory()->create(['user_id' => null]);

    $enrollment = Enrollment::factory()->create([
        'learner_id' => $learner->id,
        'section_id' => $section->id,
        'school_year_id' => $schoolYear->id,
        'status' => 'active',
    ]);

    $this->actingAs(passwordResetAdmin())
        ->post(route('students.reset-password', $enrollment->id))
        ->assertRedirect(route('students'))
        ->assertSessionHas('error');
});
