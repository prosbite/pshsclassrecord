<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentPageController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/students', [LearnerController::class, 'index'])->name('students');
    Route::post('/students/bulk-register', [EnrollmentController::class, 'bulkRegister'])->name('students.bulk-register');
    Route::get('/assessments/create', [AssessmentPageController::class, 'create'])->name('assessments.create');
    Route::get('/assessments/section-learners', [AssessmentController::class, 'sectionLearners'])->name('assessments.section-learners');
    Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
    Route::get('/assessments/summary', [AssessmentPageController::class, 'summary'])->name('assessments.summary');
    Route::get('/assessments/{assessment}', [AssessmentPageController::class, 'show'])->name('assessments.show');
    Route::get('/assessments', [AssessmentPageController::class, 'index'])->name('assessments.index');
});

require __DIR__.'/auth.php';
