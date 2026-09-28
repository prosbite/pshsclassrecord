# Student Remediation (Answer + Review)

Builds on the implemented admin MCQ/math work (`.kilo/plans/1790314961341-question-types-mcq-plan.md`). This iteration makes dispatched remediation visible to students: they answer MCQs (system-scored) and free-text (teacher-scored), and the teacher sees, in the dispatch list, who has finished.

## Locked Decisions

1. **Answers live on the frozen snapshot**: add `selected_option_id` (MCQ, FK → `exercise_session_question_options`, nullOnDelete) and `response_text` (text) to `exercise_session_questions` — one answer per frozen question, same row that carries the score.
2. **MCQ auto-scored on submit**: a selected option sets `score = points` when `is_correct`, else `0`, and stamps `graded_at`. Unanswered MCQ → `score = null`. Free-text is never auto-scored; the teacher enters it and may override any MCQ. Remediation still never feeds quarter grades.
3. **Status flow `assigned → submitted → completed`** with a new `submitted_at`. Student submit sets `submitted` + `submitted_at`; the teacher marks `completed` (existing admin control).
4. **Reveal on completion**: students see per-question marks, total, and percent only once the session is `completed`. Before that they see their saved answers only — never correctness or `answer_key`.
5. **Editable until completed**: a student may edit and resubmit while status is `assigned` or `submitted`; the page becomes read-only at `completed`. Each resubmit recomputes MCQ auto scores (teacher should mark `completed` when done — see Risks).
6. **Entry points**: a status-aware clickable tag on each visible dashboard assessment row + a dashboard Remediation panel + a dedicated organized `/student/remediation` page (ongoing vs completed) + a per-session answer page.
7. **Text input is a plain textarea** (no MathLive for students, no uploads). Math rendering reuses `MathText` for prompts/options/answers.
8. **Admin keeps full data**: the teacher's session page shows the student's selected option and typed answer, with the correct option/answer key, and can override scores.
9. **Ownership is enforced per request** (no policies exist): a student may only touch a session whose `learner.user_id` matches theirs.

## Schema (additive, continue `2026_09_26_*`)

- `exercise_session_questions`: `selected_option_id` nullable FK → `exercise_session_question_options` (explicit short name `esq_selected_option_fk`, `nullOnDelete`); `response_text` nullable text.
- `exercise_sessions`: `submitted_at` nullable timestamp.

## Models

- `ExerciseSessionQuestion`: add `selected_option_id`, `response_text` to `$fillable`; `selectedOption(): BelongsTo`; cast `selected_option_id => integer`.
- `ExerciseSession`: add `submitted_at` to `$fillable`, cast `datetime`; add `answered_count` to `$appends` (count of `sessionQuestions` with `selected_option_id !== null` OR non-blank `response_text`). The relation is already loaded in list/show paths where this is used.
- No changes to `User`.

## Requests

- New `StudentRemediationSubmitRequest`:
  - rules: `answers` nullable array; `answers.*.session_question_id` required integer; `answers.*.selected_option_id` nullable integer; `answers.*.response_text` nullable string.
  - `after()`: each `session_question_id` must belong to the route session; each `selected_option_id` must belong to that same snapshot question (mirrors the score ownership check pattern).
  - `authorize()`: `route('exerciseSession')->learner->user_id === $request->user()->id`.
- `ExerciseSessionUpdateRequest`: extend `status` to `in:assigned,submitted,completed`.

## Services

- `ExerciseSessionService::submitAnswers(ExerciseSession $session, array $entries): void`:
  - for every snapshot question, resolve the entry (if any) and set `selected_option_id` / trim-and-null `response_text`, clearing both when absent;
  - MCQ: if a selected option exists → `score = is_correct ? points : 0`, `graded_at = now()`; otherwise `score = null`, `graded_at = null`;
  - text: leave `score`/`graded_at` untouched;
  - set `status = 'submitted'` and `submitted_at = now()` (only from `assigned`; keep existing `submitted_at` otherwise).
- `syncScores` is unchanged and remains the teacher's grading path.

## Controllers

- New `StudentRemediationController`:
  - `index()`: current user's learner sessions (via `assessment`), eager `assessment.assessmentType`, `assessment.quarter`, `sessionQuestions`; transform to `{ id, assessment {id,title,type,quarter}, status, submitted_at, answered_count, total_questions, percent }`; split into `ongoing` (assigned/submitted) and `completed`.
  - `show(ExerciseSession)`: ownership guard; load `questionnaires.topic`, `sessionQuestions.options`; explicit transform that **always** includes prompt/image/type/options `{id,label,position}` and the student's saved answer, and includes `score`/`points` + session `percent` **only when `completed`**. Never emits `is_correct`, `answer_key`, `source_option_id`, or `source_question_id`.
  - `submit(StudentRemediationSubmitRequest)`: abort if `status === 'completed'`; `submitAnswers`; redirect to `student.remediation.show` with success.
- `StudentDashboardController@index`: also load the learner's sessions and pass `remediationByAssessment` (`assessmentId → { id, status }`) plus a short `remediationSummary` (ongoing/completed counts).
- `AssessmentRemediationController@show`: session rows already load status; ensure `answered_count` and `submitted_at` are available for the dispatch list.
- `ExerciseSessionController@show` (admin): surface each question's `selected_option_id` and `response_text` (model already fills them).

## Routes

Extend the existing student group (keep `student.dashboard`):

```php
Route::middleware(['auth', 'verified', EnsureUserIsStudent::class])->group(function () {
    Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/student/remediation', [StudentRemediationController::class, 'index'])->name('student.remediation.index');
    Route::get('/student/remediation/{exerciseSession}', [StudentRemediationController::class, 'show'])->name('student.remediation.show');
    Route::put('/student/remediation/{exerciseSession}', [StudentRemediationController::class, 'submit'])->name('student.remediation.submit');
});
```

## Vue

- `Pages/Students/Remediation/Index.vue`: `StudentLayout`; two organized sections (Ongoing / Completed) as cards or a table — assessment, type, status badge, answered/total, percent when completed, link to open; empty state.
- `Pages/Students/Remediation/Show.vue`: `StudentLayout`; questions grouped by the session's questionnaires; MCQ radios (labels via `MathText`, prefilled), text textareas (prefilled); a single "Submit answers" action; read-only banner when completed; when completed also show per-question `score / points` and the session total/percent. Never renders correct-answer markers.
- `Pages/Students/Dashboard.vue`: add a status-aware tag on each entry row (`remediationByAssessment[item.assessmentId]`, using `item.assessmentId` already on grade entries): "Remediation available" (assigned), "Remediation submitted", "Remediation completed", each an Inertia `Link`. Add a Remediation panel near the top with ongoing/completed counts and a link to `student.remediation.index`.
- `Pages/ExerciseSessions/Show.vue` (admin): highlight the student's selected option and show `response_text`; mark the correct MCQ and keep the score input (prefilled from the auto score); add a `Submitted` option to the status select.
- `Pages/Assessments/Remediation.vue` (admin dispatch list): distinct badge/color for `submitted` and an "Answered x/y" column so the teacher can see who finished; keep the existing Score link.

## Factories & Tests

- `ExerciseSessionQuestionFactory`: default `selected_option_id => null`, `response_text => null`.
- New `tests/Feature/Exercises/StudentRemediationTest.php` (Pest, MySQL override):
  - non-student (admin, plain user) forbidden on all student remediation routes; student with no learner gets an empty index.
  - ownership: student A cannot `show` or `submit` student B's session (403).
  - index splits ongoing/completed and reports answered/total.
  - `show` hides `is_correct`, `answer_key`, and scores before completion but includes saved answers.
  - submit stores `selected_option_id` + `response_text`; auto-scores MCQ (correct = points, wrong = 0, unanswered = null) and sets `status = submitted`, `submitted_at`.
  - submit is rejected once `completed`.
  - after the teacher completes, `show` reveals per-question scores and percent while still hiding correctness markers.
  - validation: a foreign question id or an option from another question is rejected.
  - admin session page exposes submitted status and answered count; teacher override + complete persists.
  - existing suites stay green.

## Out of Scope

- Auto-grading free text; photo/file uploads by students; notifications; grade-chain integration; multi-answer MCQ; a formal "return for revision" action (status can still be set back manually).

## Risks

- **Resubmit vs override**: a resubmit recomputes MCQ auto scores for answered MCQs, so a teacher override made before `completed` can be overwritten. Teacher should mark `completed` when done; noted in the admin UI copy.
- **Option deletion**: `selected_option_id` nulls when a snapshot option is removed (answer becomes unanswered) via `nullOnDelete`.
- **Serialization leakage**: student transforms must be explicit allow-lists; never return `is_correct`/`answer_key`.
- **Ownership**: enforce on every student route since no policies/global scopes exist.

## Validation

- `php artisan migrate` on dev DB; `php artisan test tests/Feature/Exercises` with `DB_CONNECTION=mysql DB_DATABASE=class_record_test` (no `pdo_sqlite` on this host).
- `php vendor/bin/pint` on changed PHP; `npm run build`.
- Manual smoke: dispatch a session with an MCQ + a text question → student tag/panel/index appear → answer and submit (MCQ auto-marked) → teacher sees "Submitted"/answered count, views the answer, overrides if needed, sets Completed → student sees marks/total/percent and the page is read-only.
