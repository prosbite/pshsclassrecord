<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentPageController;
use App\Http\Controllers\AssessmentRemediationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExerciseSessionController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LoginTrackerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuarterlyAssessmentController;
use App\Http\Controllers\QuarterlyAssessmentPageController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentImpersonationController;
use App\Http\Controllers\StudentRemediationController;
use App\Http\Controllers\TopicController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStudent;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    if (auth()->user()->role === 'student') {
        return redirect()->route('student.dashboard');
    }

    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified', EnsureUserIsStudent::class])
    ->group(function () {
        Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('/student/remediation', [StudentRemediationController::class, 'index'])->name('student.remediation.index');
        Route::get('/student/remediation/{exerciseSession}', [StudentRemediationController::class, 'show'])->name('student.remediation.show');
        Route::put('/student/remediation/{exerciseSession}', [StudentRemediationController::class, 'submit'])->name('student.remediation.submit');
    });

Route::prefix('admin')
    ->middleware(['auth', 'verified', EnsureUserIsAdmin::class])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::resource('topics', TopicController::class)->except(['show']);
        Route::resource('questionnaires', QuestionnaireController::class)->except(['show']);
        Route::resource('questions', QuestionController::class)->except(['show']);
        Route::get('/exercise-sessions/{exerciseSession}', [ExerciseSessionController::class, 'show'])
            ->name('exercise-sessions.show');
        Route::put('/exercise-sessions/{exerciseSession}', [ExerciseSessionController::class, 'update'])
            ->name('exercise-sessions.update');
        Route::patch('/exercise-sessions/{exerciseSession}', [ExerciseSessionController::class, 'update']);
        Route::delete('/exercise-sessions/{exerciseSession}', [ExerciseSessionController::class, 'destroy'])
            ->name('exercise-sessions.destroy');
        Route::get('/students', [LearnerController::class, 'index'])->name('students');
        Route::get('/login-tracker', [LoginTrackerController::class, 'index'])->name('login-tracker.index');
        Route::post('/students/{enrollment}/login', [StudentImpersonationController::class, 'store'])
            ->name('students.impersonate');
        Route::post('/students/bulk-register', [EnrollmentController::class, 'bulkRegister'])->name('students.bulk-register');
        Route::post('/students/bulk-update-emails', [EnrollmentController::class, 'bulkUpdateEmails'])->name('students.bulk-update-emails');
        Route::get('/assessments/create', [AssessmentPageController::class, 'create'])->name('assessments.create');
        Route::get('/assessments/section-learners', [AssessmentController::class, 'sectionLearners'])->name('assessments.section-learners');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/summary', [AssessmentPageController::class, 'summary'])->name('assessments.summary');
        Route::get('/assessments/{assessment}/remediation', [AssessmentRemediationController::class, 'show'])
            ->name('assessments.remediation');
        Route::post('/assessments/{assessment}/remediation/sessions', [AssessmentRemediationController::class, 'storeSessions'])
            ->name('assessments.remediation.sessions.store');
        Route::get('/assessments/{assessment}/edit', [AssessmentPageController::class, 'edit'])->name('assessments.edit');
        Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
        Route::patch('/assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
        Route::get('/assessments/{assessment}', [AssessmentPageController::class, 'show'])->name('assessments.show');
        Route::get('/assessments', [AssessmentPageController::class, 'index'])->name('assessments.index');
        Route::get('/quarterly-assessments', [QuarterlyAssessmentPageController::class, 'index'])
            ->name('quarterly-assessments.index');
        Route::get('/quarterly-assessments/upload', [QuarterlyAssessmentPageController::class, 'upload'])
            ->name('quarterly-assessments.upload');
        Route::get('/quarterly-assessments/{quarterlyAssessment}', [QuarterlyAssessmentPageController::class, 'show'])
            ->name('quarterly-assessments.show');
        Route::post('/quarterly-assessments', [QuarterlyAssessmentController::class, 'store'])
            ->name('quarterly-assessments.store');
        Route::delete('/quarterly-assessments/{quarterlyAssessment}', [QuarterlyAssessmentController::class, 'destroy'])
            ->name('quarterly-assessments.destroy');
        Route::patch('/quarterly-assessments/{quarterlyAssessment}', [QuarterlyAssessmentController::class, 'update'])
            ->name('quarterly-assessments.update');
    });

Route::middleware('auth')
    ->post('/impersonation/stop', [StudentImpersonationController::class, 'destroy'])
    ->name('impersonation.stop');

require __DIR__.'/auth.php';
