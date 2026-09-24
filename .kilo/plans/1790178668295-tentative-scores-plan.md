# Per-Student Tentative Scores

Follow-up to `.kilo/plans/1790177295572-assessments-revamp-plan.md` (structured assessments as source of truth).

## Goal

Let admins mark an individual learner's score on an assessment as **tentative** (provisional), including learners who have not been officially scored yet. Tentative scores are stored per learner per assessment, still count in the grade math exactly like final scores, and are visually flagged everywhere scores are shown.

## Locked Decisions

1. **Storage**: add a `tentative` boolean column (1/0, default 0) to `assessment_learners`. One value per learner per assessment.
2. **Grade math**: tentative entries are **included** in segment totals / W% / TW% / GE / final chain, unchanged. Only flag them.
3. **Input surface**: tentative is set in the assessment create/edit form only, via a per-learner checkbox plus a master "toggle all" checkbox. Summary/Dashboard/Detail are display-only.
4. **Flagging**: raw per-assessment score cells get the tentative marker. Aggregate columns are not tinted (a quarter-level `hasTentative` signal may drive a small notice, but no aggregate coloring).
5. **No null scores**: `score` stays numeric (default 0). Tentative is a flag on an entered value; a blank input still saves 0.
6. **Non-destructive writes unchanged**: update keeps the existing no-op-on-empty + `syncWithoutDetaching` behavior.

## Data Model

- Migration `add_tentative_to_assessment_learners_table`: `boolean('tentative')->default(false)->after('score')`; `down()` drops it. Additive, all existing rows default to 0.
- `Assessment::learners()` and `Learner::assessments()` → `withPivot('score', 'tentative')` so eager loads carry the flag automatically.

## Data Flow

```
AssessmentForm (checkbox per learner + toggle all)
        │  learner_scores[].tentative
        ▼
AssessmentController store/update → pivot { score, tentative }
        │
        ├─ AssessmentPageController@summary  → assessments[].learners[].pivot.{score,tentative}
        ├─ AssessmentPageController@edit     → learnerScores + learnerTentatives
        ├─ AssessmentPageController@show     → learners[].tentative
        └─ StudentDashboardController@index  → assessments[].tentative
                    │
                    ▼
        assessmentGrading.js (entry.tentative, tentativesByLearner, row.tentatives, hasTentative)
                    ▼
        Summary.vue / Dashboard.vue / AssessmentDetail.vue (display flags)
```

## Work Plan

### Phase 0 — Schema and models
- **T0.1** Migration `database/migrations/<ts>_add_tentative_to_assessment_learners_table.php` with the column above.
- **T0.2** Add `'tentative'` to the `withPivot(...)` in `app/Models/Assessment.php` and `app/Models/Learner.php`.

### Phase 1 — Shared grading module (`resources/js/Composables/assessmentGrading.js`)
- **T1.1** `buildEntry(...)` gains `tentative` (boolean) on every score entry.
- **T1.2** Add `buildTentativesByLearner(assessments)` → `{ [learnerId]: { [assessmentId]: boolean } }`; read `learner.pivot.tentative ?? false`.
- **T1.3** Add `resolveTentative(assessment, tentativesByLearner, learnerId)`; fall back to a top-level `assessment.tentative` (student payload), else `false`.
- **T1.4** `buildSegmentEntries(grouped, scoresByLearner, learnerId, tentativesByLearner = {})` — pass tentative into each entry.
- **T1.5** `buildQuarterResult(grouped, scoresByLearner, learnerId, previousQuarterGe = null, tentativesByLearner = {})`.
- **T1.6** `buildQuarterResultFromEntries(entries, previousQuarterGe)` sets `hasTentative` = any `entry.tentative`; all metrics stay as-is (tentative counts).
- **T1.7** `buildSummaryRows(...)` builds tentatives internally from current + previous assessments; each row adds `tentatives` keyed by assessmentId. Do **not** change `buildScoresByLearner` (stays numeric) or `metricsForEntries`.

### Phase 2 — Server
- **T2.1** `AssessmentRequest`: add `'learner_scores.*.tentative' => ['nullable', 'boolean']`.
- **T2.2** `AssessmentController` `pivotPayload()`: include `'tentative' => (bool) ($item['tentative'] ?? false)`.
- **T2.3** `AssessmentPageController@edit`: also pass `learnerTentatives` to the `Assessments/Edit` page (`mapWithKeys` string learner id → bool). `AssessmentForm` consumes it via its `initialTentatives` prop.
- **T2.4** `AssessmentPageController@show`: add `'tentative' => (bool) ($learner->pivot->tentative ?? false)` to the mapped learner.
- **T2.5** `StudentDashboardController@transformAssessment`: add `'tentative' => (bool) ($match?->pivot?->tentative ?? false)`.
- Summary needs no controller change beyond the model `withPivot` update.

### Phase 3 — Assessment form (`AssessmentForm.vue`, `Edit.vue`)
- **T3.1** Add `learnerTentatives` reactive map; seed in `resetLearnerScores` from `props.initialTentatives` (missing → `false`). Reset to `false` on section change when not editing.
- **T3.2** New `initialTentatives` prop; `Edit.vue` passes `:initial-tentatives="props.learnerTentatives"`.
- **T3.3** Learner table: add a "Tentative" column with a per-row checkbox; add a master checkbox in that column's header with an indeterminate state driven by the map. Clicking the master sets every loaded learner to the new value; individual toggles update the master's computed state. Hide the master when there are no learners.
- **T3.4** `handleSubmit`: emit `tentative: Boolean(value)` in each `learner_scores` entry.
- **T3.5** CSV importer: support an optional `tentative` column (accept `1/0/true/false/yes/no`, case-insensitive). If the column is absent, every row defaults to `false`. Mention the optional column in the helper text.
- **T3.6** When loading a learner list in edit mode, seed from `initialTentatives`; do not clobber already-entered values on unrelated re-renders.

### Phase 4 — Display
- **T4.1** `AssessmentSummary.vue`: add `isTentative(row, column)` = `column.type === 'score' && row.tentatives?.[column.assessmentId]`; apply amber cell styling and a small `T` marker to those cells only. Add a short legend ("T = tentative score"). Leave T/%/W%/GE/TW/Trunc/Final cells unchanged.
- **T4.2** `Dashboard.vue`: build `tentativesByLearner` (from `assessment.tentative`), pass it into `buildSegmentEntries` and the simulation entries. Restore the amber card + "Tentative Score" badge on tentative detail entries (`item.tentative`). Optionally show a small "Includes tentative scores" note on the Final Grade Overview when `selectedQuarterResult.hasTentative`.
- **T4.3** `AssessmentDetail.vue`: show a "Tentative" badge next to tentative learner scores in the score table.

### Phase 5 — Tests and validation
- **T5.1** Pest (`tests/Feature/Assessments/AssessmentManagementTest.php`): `store`/`update` persist `tentative` on the pivot (true and false); `edit` page exposes `learnerTentatives`; `summary` assessment learner pivot includes `tentative`; `student.dashboard` assessment exposes `tentative`; a `tentative` CSV/boolean is accepted by `AssessmentRequest`.
- **T5.2** Vitest (`resources/js/Composables/__tests__/assessmentGrading.spec.js`): entries carry `tentative`; tentative entries still count in `metricsForEntries` / `TW%`; `buildTentativesByLearner`; `buildQuarterResult`/`FromEntries` `hasTentative`; `buildSummaryRows` row `tentatives`.
- **T5.3** Run: `npx vitest run`, `php artisan test` (this machine has no `pdo_sqlite`; run Pest with `DB_CONNECTION=mysql DB_DATABASE=class_record_test`), `php vendor/bin/pint` on changed PHP, `npm run build`.

## Edge Cases and Failure Modes

- **No learners in section**: master checkbox hidden; nothing to toggle.
- **Section change**: tentative map resets to initial (edit) or false (create).
- **Legacy rows**: `tentative` defaults to 0 → displayed as final, no behavior change.
- **CSV without `tentative` column**: all rows false; existing imports unchanged.
- **Boolean coercion**: accept `true/false/1/0/"1"/"0"` from requests and CSV; always store a real boolean.
- **Tentative still counts**: segments/TW%/GE/final are unaffected by the flag; this is intentional.
- **Non-destructive update**: unchanged (`syncWithoutDetaching`, empty payload no-op) so toggling tentative cannot wipe scores.
- **Unsaved toggle-all + validation error**: form re-render must keep the checkbox state (state lives in the reactive map, not the DOM).

## Rollout / Rollback

- Additive migration only; existing pivot rows get `tentative = 0`. No backfill needed.
- Rollback: run `php artisan migrate:rollback` (drops the column); application code then ignores the removed flag.

## Out of Scope

- Editing tentative inline on the Summary/Dashboard (display-only surfaces).
- Nullable/blank `score` ("not yet scored" as distinct from 0).
- Excluding tentative from the grade.
- Marking aggregate columns (T/%/W%/GE/TW%) as tentative.
- Legacy `quarterly_assessments` "(T)" header handling (untouched).

## Validation Summary

- Schema/persistence verified by Pest using the pivot assertions.
- Payload flags verified by Inertia assertions on summary/edit/dashboard.
- Flag propagation and continued counting verified by Vitest.
- Manual smoke: create an assessment, tick tentative for one learner and "toggle all", save; confirm the Summary score cell shows the tentative marker, the same learner's Dashboard card shows "Tentative Score", and TW%/GE are unchanged versus an unticked score.

## Assumption

`score` remains a required numeric value defaulting to 0; tentative is a flag on that value. If "students who don't have scores yet" needs a distinct blank/`null` state (dash instead of 0), that is a separate change to the score column and form validation.
