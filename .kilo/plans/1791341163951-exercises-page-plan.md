# Dedicated Exercises Page (admin)

Add an admin "Exercises" area that browses/manages exercise sessions by section +
assessment, reuses the assessment page's session table and exercise-creation form, and
links out to per-student exercise history and the existing session contents page.

## Locked decisions (from planning Q&A)

1. **Tabs = class sections that have assessments** for the current school year (not every
   section). Derived from the assessments list, so a section with no assessments is not a tab.
2. **Dropdown per tab = that section's saved assessments**, labelled with the assessment
   title (fallback to its type name when title is empty).
3. **Preventive / Enhancement toggle** filters the session list by `ExerciseSession.kind`.
4. **Session table = same as the assessment page's sessions table** (Learner, Kind,
   Questionnaires, Answered, Attempted, Percent, Status, Actions) with full parity:
   open session, delete one, delete all.
5. **Clicking a student name** → new per-student exercise history page (all of that
   learner's sessions across assessments).
6. **Clicking an exercise/session** → existing `ExerciseSessions/Show.vue`
   (`exercise-sessions.show`); reuse, do not rebuild.
7. **Create flow** = a "Create Exercise" button leading to a separate create page with
   section → assessment dropdowns, then the same creation UI as the assessment page
   (learner selection + kind + questionnaire checkboxes + create button).
8. **After create**, redirect to the Exercises list pre-filtered to the chosen
   section + assessment + kind, with a success flash (created/skipped counts).
9. **Implementation approach for shared UI: copy** the assessment page's session table
   and creation form into new components (matches the request "just copy the creation of
   exercise in the assessment page"). The existing `Assessments/Remediation.vue` is left
   unchanged to avoid regressions; the backend create logic is shared via a service method.

## Out of scope

- Changing `Assessments/Remediation.vue` or its routes/behavior.
- Fixing the student 500 from the earlier error-log work.
- Pagination of sessions (assessment sessions are already loaded in full; keep parity).
- Filters beyond section / assessment / kind.

## Routes (inside the existing `Route::prefix('admin')` + `EnsureUserIsAdmin` group in `routes/web.php`)

```php
Route::get('/exercises', [ExercisePageController::class, 'index'])->name('exercises.index');
Route::get('/exercises/create', [ExercisePageController::class, 'create'])->name('exercises.create');
Route::post('/exercises/{assessment}/sessions', [ExercisePageController::class, 'store'])
    ->name('exercises.sessions.store');
Route::get('/exercises/students/{learner}', [ExercisePageController::class, 'student'])
    ->name('exercises.students.show');
Route::delete('/exercises/sessions/{exerciseSession}', [ExercisePageController::class, 'destroy'])
    ->name('exercises.sessions.destroy');
Route::delete('/exercises/{assessment}/sessions', [ExercisePageController::class, 'destroyAll'])
    ->name('exercises.sessions.destroy-all');
```

Declare static/`create`/`students` paths before parameterized ones is not strictly required
(no `/exercises/{x}` catch-all), but keep the order above for clarity. Rename if
`exercises.sessions.destroy-all` vs the store route (`POST` vs `DELETE` on
`/exercises/{assessment}/sessions`) needs distinguishing — HTTP method already separates them.

## Backend

### `app/Http/Controllers/ExercisePageController.php` (new)

Constructor injects `AssessmentRemediationService`, `ExerciseSessionService`.

Shared private helper `sectionTabs()` returns sections (for the current school year) that
have ≥1 assessment, each with nested assessments:

```php
[
  ['id' => .., 'section_name' => .., 'grade_level' => ..,
   'assessments' => [ ['id'=>.., 'title'=>.., 'type'=>.., 'quarter'=>.., 'assessment_date'=>..], ... ]],
  ...
]
```

- `index(Request $request)` → `Inertia::render('Exercises/Index', [...])`
  - Query: `section`, `assessment`, `kind` (`preventive` default).
  - Resolve selected section (default: first tab) and selected assessment (default: first
    assessment of the selected section) so the page shows data on first load.
  - Validate the selected assessment belongs to the selected section; otherwise fall back.
  - `sessions` = the selected assessment's exercise sessions where `kind` matches, loaded
    with `learner` + `questionnaires` + `sessionQuestions`, ordered `created_at asc, id asc`
    (mirror `AssessmentRemediationController::show`), mapped to a lean payload:
    `id, kind, status, percent, attempted_count, answered_count, total_questions,
    questionnaires_count, learner: { id, name_last_first, username }`.
  - Props: `sections` (tabs), `selectedSectionId`, `selectedAssessmentId`, `kind`, `sessions`.
- `create(Request $request)` → `Inertia::render('Exercises/Create', [...])`
  - Query: `section`, `assessment`.
  - Props always: `sections` (tabs with nested assessments), `selectedSectionId`,
    `selectedAssessmentId`.
  - When a valid `assessment` (belonging to `selectedSectionId`) is selected, also include
    `assessment` (summary), `failingLearners` (via `AssessmentRemediationService::failingLearners`),
    `threshold` (`Setting::passingThreshold()`), `poolQuestionnaires` (the assessment's
    questionnaires), `bankQuestionnaires` (all questionnaires with topic) — mirror
    `AssessmentRemediationController::show` props.
- `store(Request $request, Assessment $assessment)` → validates the same payload as
  `AssessmentRemediationController::storeSessions` (`learner_ids`, `questionnaire_ids`,
  `kind`), calls the shared service method, then redirects to `exercises.index` with
  `section => $assessment->section_id, assessment => $assessment->id, kind => $kind` and a
  success flash identical in wording to the assessment page.
- `student(Learner $learner)` → `Inertia::render('Exercises/Student', [...])`
  - `learner` = `{ id, name, email }`.
  - `sessions` = `$learner->exerciseSessions()->with('assessment.assessmentType',
    'assessment.section', 'assessment.quarter', 'sessionQuestions')->orderByDesc('created_at')
    ->orderByDesc('id')->get()` mapped with assessment title/type/section/quarter, kind,
    status, percent, answered/total, attempted, timestamps. (Mirror the summary shape in
    `StudentRemediationController::transformSummary`; this page only lists sessions, so no
    `is_correct` / answer-key payload is needed.)
- `destroy(Request $request, ExerciseSession $exerciseSession)` → delete, redirect to
  `exercises.index` with `section => $exerciseSession->assessment?->section_id`,
  `assessment => $exerciseSession->assessment_id`, `kind => $request->input('kind',
  ExerciseSession::KIND_PREVENTIVE)`, success flash.
- `destroyAll(Request $request, Assessment $assessment)` → `$assessment->exerciseSessions()->delete()`,
  redirect to `exercises.index` with the same filter shape + count flash.

### `app/Services/AssessmentRemediationService.php` (refactor, low risk)

Extract the create loop from `AssessmentRemediationController::storeSessions` into:

```php
/** @return array{created: int, skipped: int} */
public function assignSessions(
    Assessment $assessment,
    array $learnerIds,
    array $questionnaireIds,
    ?int $userId,
    string $kind = ExerciseSession::KIND_PREVENTIVE,
): array
```

- Keep the existing dedupe-by-learner behavior (`preventive` vs `enhancement` does not
  change the one-session-per-learner-per-assessment rule).
- `AssessmentRemediationController::storeSessions` calls it and rebuilds its existing
  message (`"{$created} {$kind} session(s) created"` + skipped suffix) so
  `tests/Feature/Exercises/RemediationTest.php` still passes unchanged.

## Frontend

### `resources/js/Layouts/MainAuthLayout.vue`

- Import `AcademicCapIcon` from `@heroicons/vue/24/outline`.
- `const exercisesPath = normalizePath(route('exercises.index'))`
- `const isExercisesActive = computed(() => currentPath.value.startsWith(exercisesPath))`
- Add nav entry after `Assessments`:
  `{ label: 'Exercises', href: exercisesPath, active: isExercisesActive.value, icon: AcademicCapIcon }`
- `startsWith('/admin/exercises')` keeps the item active on the create and student pages.

### Admin toast notifications — `resources/js/Layouts/MainAuthLayout.vue`

Admin pages currently have no toast; `StudentLayout.vue` already implements one. Mirror that
pattern in the admin layout so create/delete feedback is surfaced site-wide.

- Add `watch` + `onBeforeUnmount` to the existing imports from `vue` (`computed`, `ref`,
  `onMounted`, `onUnmounted` are already imported); `usePage` is already imported.
- Add `flashSuccess` / `flashError` computed from `page.props.flash?.success` /
  `.error` (shared via `HandleInertiaRequests`).
- Add a `toast` ref and `showToast(message, type)` with a ~4s auto-dismiss `toastTimer`;
  `watch([flashSuccess, flashError], ([success, error]) => { success ? showToast(success,
  'success') : error && showToast(error, 'error'); }, { immediate: true })`; clear the timer
  in `onBeforeUnmount` (same logic as `StudentLayout.vue:11-45`).
- Render the toast inside the layout root: a `<Transition>` with a fixed top-right panel
  (`fixed right-6 top-6 z-[60]`), success/error border + dot styling, message, and a close
  button (copy the markup from `StudentLayout.vue:54-83`).
- Because admin flash now surfaces as a toast, remove the duplicate inline
  `flash.success` / `flash.error` banners from `resources/js/Pages/ErrorLogs/Index.vue`
  (added in the earlier error-log work) to avoid double messaging. The new Exercises pages
  must not render their own inline flash banners either.

### `resources/js/Pages/Exercises/Index.vue` (new)

- `MainAuthLayout`; header card (rounded-3xl, slate/indigo, same family as Tracker/ErrorLogs).
- Tabs = `sections`; tab click → `router.get(route('exercises.index'), { section, assessment:
  firstAssessmentOfSection }, { preserveState: true, replace: true })`.
- Assessment `<select>` for the active tab (options from nested `assessments`, label = `title`
  or type fallback); change → router.get with `section` + `assessment`.
- Preventive / Enhancement buttons (two-toggle, same style as the assessment page's radio
  labels) → router.get with `kind`.
- "Create Exercise" button → `router.get(route('exercises.create'), { section: selectedSectionId })`.
- Session table mirrors `Assessments/Remediation.vue` sessions table (columns and status/kind
  badges). Row cells:
  - Learner name is a `<Link :href="route('exercises.students.show', session.learner.id)">`.
  - Actions: "Open" → `<Link :href="route('exercise-sessions.show', session.id)">`; "Delete" →
    `window.confirm` then `router.delete(route('exercises.sessions.destroy', session.id),
    { data: { kind }, preserveScroll: true })`.
  - Header "Delete all" → `window.confirm` then
    `router.delete(route('exercises.sessions.destroy-all', selectedAssessmentId),
    { data: { kind }, preserveScroll: true })`.
- Empty states: no section tabs; selected assessment has no sessions of the chosen kind.
- Rely on the admin toast (from `MainAuthLayout`) for `flash.success` / `flash.error` after
  delete / delete-all — do not render inline banners on this page.

### `resources/js/Pages/Exercises/Create.vue` (new)

- Section `<select>` (from `sections`) → on change, router.get `{ section }` (clears
  assessment).
- Assessment `<select>` (options from the selected section's nested assessments) → on change,
  router.get `{ section, assessment }`.
- When `assessment` prop is present, render the copied assignment UI:
  - Kind radio (Preventive/Enhancement) with kind-based learner preselection.
  - Learner table (name, score, percent, failing/passing, tentative badge) with select-all.
  - Questionnaire checkboxes (from `bankQuestionnaires`).
  - "Create sessions" button → `useForm({ learner_ids, questionnaire_ids, kind }).post(
    route('exercises.sessions.store', assessment.id))`.
- Copy the relevant markup/script from `resources/js/Pages/Assessments/Remediation.vue`
  (learner table + disseminate form). Do **not** alter `Remediation.vue`.
- Show an empty state until an assessment is selected.

### `resources/js/Pages/Exercises/Student.vue` (new)

- `MainAuthLayout`; header with learner name/email.
- List of all sessions (newest first): assessment title/type/section/quarter, kind badge,
  status badge, answered/total, percent, created/completed timestamps.
- Each row → `<Link :href="route('exercise-sessions.show', session.id)">` to view contents.
- Back link to `exercises.index`.
- Read-only (no delete) on this page.

## Tests — `tests/Feature/Exercises/ExercisePageTest.php` (new)

Use the repo MySQL convention (`DB_CONNECTION=mysql DB_DATABASE=class_record_test`); run
`npm run build` before page-render assertions. Build fixtures like
`tests/Feature/Exercises/RemediationTest.php` (school year, grade level, section,
assessment type, admin, learners + enrollments, questionnaire). Do **not** redeclare existing
Pest helper names from `tests/Feature/Exercises/*` (`makeRemediationAssessment`,
`addRemediationQuestion(s)`, `addRemediationMcq`, `failingRow`, `sr*`).

- Admin can open `exercises.index` → `assertOk` + `assertInertia(component('Exercises/Index'),
  has('sections'), has('sessions'))`.
- Non-admin (student) forbidden on `exercises.index`, `exercises.create`, and
  `exercises.students.show`.
- Kind filter: create one preventive + one enhancement session; `?kind=enhancement` returns
  only the enhancement session (assert session ids/kind in payload).
- Section/assessment resolution: `?section=&assessment=` selects that assessment's sessions;
  an assessment not belonging to the section falls back to the section default.
- `exercises.create` renders; with `?assessment=` includes `failingLearners`,
  `threshold`, `bankQuestionnaires`.
- `exercises.sessions.store` creates a session and redirects to `exercises.index` with
  `section`/`assessment`/`kind`; assert the session row exists.
- `exercises.students.show` returns all of a learner's sessions across two assessments.
- `exercises.sessions.destroy` deletes one row; `exercises.sessions.destroy-all` deletes all
  rows for the assessment. Both assert redirect.
- Regression: run `tests/Feature/Exercises/RemediationTest.php` and
  `tests/Feature/Exercises/StudentRemediationTest.php` unchanged after the service refactor.

## Verification order

1. `php artisan migrate --force` (no new migrations expected for this feature).
2. `php vendor/bin/pint` on changed/new PHP.
3. `npm run build` (new Vue pages must be in the Vite manifest before page tests/deploy).
4. `DB_CONNECTION=mysql DB_DATABASE=class_record_test php artisan test
   tests/Feature/Exercises/ExercisePageTest.php` plus the two regression suites in
   `tests/Feature/Exercises/`.
5. Manual smoke as admin: `/admin/exercises` → section tabs, assessment dropdown,
   preventive/enhancement toggle, table parity; student name → student history; Open →
   session contents; delete one + delete all (each should raise the new admin success toast).
   Then `/admin/exercises/create` → section → assessment → assign learners/questionnaires →
   create → confirm redirect to the filtered list with the success toast. Also confirm the
   ErrorLogs page no longer shows a duplicate inline banner alongside the toast.

## Risks / notes

- **Global admin toast**: adding toasts to `MainAuthLayout` changes flash behavior for every
  admin page. Only `ErrorLogs/Index.vue` currently rendered inline flash banners, so those are
  removed to avoid duplication. Any future admin page that renders its own inline flash banner
  will double up with the toast.
- **Duplicate UI**: the session table and creation form are copied, so `Exercises/Index.vue` /
  `Exercises/Create.vue` and `Assessments/Remediation.vue` can drift. A follow-up refactor
  could extract `Components/Exercises/SessionTable.vue` and
  `Components/Exercises/SessionAssignmentForm.vue`; intentionally deferred to keep the
  existing page untouched.
- **Session detail delete redirect**: `ExerciseSessionController::destroy` still redirects to
  `assessments.remediation`. Deleting from the new Exercises page uses
  `exercises.sessions.destroy` instead, so the new page is unaffected. Editing scores on
  `exercise-sessions.show` returns to that page (existing behavior).
- **Assessment title duplicates**: the dropdown uses the title as the label per the request;
  if two assessments in a section share a title, they will look identical. Optional
  mitigation (not required): append `· Q{quarter}` or the assessment date to the option text.
- **Current school year only**: both tabs and assessment dropdowns are scoped to
  `SchoolYear::current()`, consistent with the rest of the admin pages.
- **Default selection**: the index auto-selects the first section tab and that section's first
  assessment so the list is populated on load; change to "select an assessment" prompt if a
  deliberate first selection is preferred.
