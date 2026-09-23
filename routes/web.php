<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentPageController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LoginTrackerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuarterlyAssessmentController;
use App\Http\Controllers\QuarterlyAssessmentPageController;
use App\Http\Controllers\StudentImpersonationController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStudent;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    ->get('/student/dashboard', [StudentDashboardController::class, 'index'])
    ->name('student.dashboard');

Route::prefix('admin')
    ->middleware(['auth', 'verified', EnsureUserIsAdmin::class])
    ->group(function () {
        Route::get('/dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('dashboard');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
