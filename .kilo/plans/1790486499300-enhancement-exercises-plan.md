# Enhancement Exercises (Exercise Kind)

Add a second exercise kind — **Enhancement** — alongside **Preventive**, assignable to any student, reusing the entire existing exercise-session machinery. This is a taxonomy change, not a new subsystem.

## Locked Decisions

1. **One session per learner per assessment.** A learner gets either a Preventive or an Enhancement session for an assessment, never both. `exercise_sessions.unique(assessment_id, learner_id)` and the dashboard aggregation stay as-is.
2. **Kind is set at creation, immutable afterward.** To switch kind, delete and recreate (in-place change is out of scope).
3. **Created from the existing per-assessment page** (`Assessments/Remediation.vue`) via a new "Exercise type" selector. No new routes/controllers.
4. **UI generalizes to "Exercises" + a per-session kind badge.** "Preventive" / "Enhancement" become badges, not page umbrellas.
5. **Keep internal route names/URLs** (`student.remediation.*`, `assessments.remediation.*`, `/student/remediation`, `/admin/assessments/{assessment}/remediation`). Only visible labels/payloads change.
6. Same status flow (`assigned → submitted → completed`), same MCQ/text grading, same auto-score, same reveal-on-completion rules for both kinds.
7. Learner pre-selection default: **Preventive → failing learners pre-selected** (unchanged); **Enhancement → none pre-selected**.
8. Existing sessions backfill to `preventive`.

## Schema

New migration (continue `2026_10_06_*`):

- `exercise_sessions.kind` — `string`, default `'preventive'`, placed after `status`. MySQL applies the default to existing rows, so no data migration is needed. No change to the unique key.

## Models

- `app/Models/ExerciseSession.php`
  - Add `kind` to `$fillable`.
  - Add constants `KIND_PREVENTIVE = 'preventive'` and `KIND_ENHANCEMENT = 'enhancement'`.
  - No cast, no `$appends` (plain string serializes automatically).
- `database/factories/ExerciseSessionFactory.php` — add `'kind' => 'preventive'` for explicitness (DB default would cover it).

## Services

- `app/Services/ExerciseSessionService.php::createFor(Assessment, Learner, array $questionnaireIds, ?int $userId, string $kind = 'preventive')`
  - Keep `firstOrCreate(['assessment_id' => ..., 'learner_id' => ...])` lookup unchanged (one-per-pair guarantee).
  - Add `'kind' => $kind` to the create attributes (so it is only set when the session is first created; existing sessions keep their kind).

## Controllers

- `app/Http/Controllers/AssessmentRemediationController.php`
  - `storeSessions()`: validate `kind` as `['nullable', 'in:preventive,enhancement']` (default `'preventive'`); pass it to `createFor()`. Skip logic unchanged.
  - Optionally include kind in the success flash (e.g. "1 enhancement session created").
  - `show()`: no structural change — `kind` serializes with each session row automatically; the sessions table can render it.
- `app/Http/Controllers/StudentDashboardController.php`
  - In the `remediationByAssessment[$assessment->assessment_id]` entry, add `'kind' => $session->kind`.
- `app/Http/Controllers/StudentRemediationController.php`
  - `transformSummary()`: add `'kind' => $session->kind`.
  - `show()` session payload: add `'kind' => $exerciseSession->kind`.
  - No change to `submit()` / allow-list / ownership logic.
- `app/Http/Controllers/DashboardController.php`
  - In the `pending` session mapping (awaiting marking), add `'kind' => $session->kind` for the admin dashboard badge.
- `app/Http/Controllers/TrackerController.php` — no change.

## Vue

Shared helper: a small `exerciseKindLabel(kind)` → `Preventive` / `Enhancement` and a badge class map (e.g. Preventive = amber/indigo, Enhancement = emerald/sky) can be inlined per file or a tiny shared composable; keep it local unless duplication becomes unwieldy.

- `resources/js/Pages/Assessments/Remediation.vue`
  - Add `kind` to the `useForm` payload (default `'preventive'`).
  - Add an "Exercise type" selector (two options) near the Disseminate section.
  - On kind change, initialize learner selection: Preventive → failing learners pre-selected; Enhancement → empty.
  - Sessions table: add a **Kind** badge column; keep the existing Answered/Attempted/Percent/Status columns.
  - Relabel umbrella text: page eyebrow "Preventive Exercises" → "Exercises"; delete-all confirm "preventive exercise session(s)" → "exercise session(s)".
- `resources/js/Pages/Students/Dashboard.vue`
  - `remediationTagLabel(status, kind)` → e.g. `Exercise available · Preventive` / `Exercise submitted · Enhancement` / `... completed`.
  - Panel eyebrow "Preventive Exercises" → "Exercises"; button "View preventive exercises" → "View exercises". Tag reads `remediationFor(item.assessmentId).kind`.
- `resources/js/Pages/Students/Remediation/Index.vue`
  - Head title / eyebrow / heading / empty state → "Exercises" wording.
  - Add a kind badge per row (Ongoing + Completed).
  - Fallback title label stays generic ("Exercise").
- `resources/js/Pages/Students/Remediation/Show.vue`
  - Eyebrow "Preventive Exercises" → "Exercises"; back link "Back to preventive exercises" → "Back to exercises"; show a kind badge near the title.
- `resources/js/Pages/ExerciseSessions/Show.vue` (admin)
  - Back link → "Back to exercises"; add a kind badge in the header.
- `resources/js/Components/Assessments/AssessmentDetail.vue`
  - Button label "Preventive Exercises" → "Exercises".
- `resources/js/Pages/Dashboard.vue` (admin)
  - Copy: "Submitted preventive exercises" / "Preventive exercises" / "Preventive exercise" → generic "exercises".
  - Add a kind badge in the Awaiting-marking rows (uses `kind` added to the controller payload).
- `resources/js/Pages/Settings/Edit.vue`
  - "Preventive exercises flag learners below this passing threshold..." → generic wording, e.g. "Exercises flag learners below this passing threshold...".

## Tests

- `tests/Feature/Exercises/StudentRemediationTest.php`
  - Assert `kind === 'preventive'` in the default create path / student payloads.
  - New test: admin creates an **enhancement** session (`kind` in the storeSessions payload) for a passing learner; assert `exercise_sessions.kind === 'enhancement'`, and that it surfaces in student index/show and the student dashboard `remediationByAssessment`.
  - New test: an enhancement requested for a learner who already has a preventive session for the same assessment is **skipped** (one-per-pair).
- `tests/Feature/Exercises/RemediationTest.php`
  - Existing tests stay green; add `kind` assertion where a created session is inspected.
- `tests/Feature/DashboardTest.php` / `tests/Feature/TrackerTest.php` — unchanged (kind is additive to payloads).
- No test references renamed routes (they are unchanged).

## Validation

- `php artisan migrate --force`.
- `DB_CONNECTION=mysql DB_DATABASE=class_record_test php artisan test tests/Feature/Exercises` plus `tests/Feature/DashboardTest.php` and `tests/Feature/TrackerTest.php` (host has no `pdo_sqlite`; do not use `phpunit.xml` sqlite config).
- `php vendor/bin/pint` on changed PHP.
- `npm run build`.
- Manual smoke: on an assessment page choose Enhancement, select passing learners, pick questionnaires, create → sessions show an Enhancement badge → student dashboard tag reads "Exercise available · Enhancement" → student answers/submits → teacher marks completed → student sees marks/percent.

## Risks

- **Kind immutability**: no UI to change kind after creation; a mis-chosen kind requires delete + recreate. Acceptable per decision 2 (note as possible follow-up).
- **Skipped enhancement**: a learner already holding a preventive session for an assessment will be skipped when enhancement is attempted for the same assessment. The admin must delete the existing session first. Surface this via the existing "skipped (already assigned)" message.
- **Pre-selection UX**: switching the type selector resets the learner selection to that kind's default; ensure this is intentional and visible (helper text).
- **Label drift**: several hardcoded "Preventive" strings exist; grep for `[Pp]reventive` after the change to confirm only intended usages remain.
- **No aggregation change**: because of decision 1, `remediationByAssessment[assessment_id]` and the unique key remain valid; do not "fix" them.

## Out Of Scope

- In-place kind change / return-for-revision flow.
- Splitting admin dashboard or tracker metrics by kind.
- Renaming routes/URLs or the `Remediation` internal identifiers.
- Kind-specific scoring, thresholds, or reveal rules.
- Enhancement tied to a passing-only gate (assignment is manual and open to any listed learner).
