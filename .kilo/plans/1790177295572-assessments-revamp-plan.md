# Assessments Module Revamp — Structured Assessments as Source of Truth

## Goal

Make individually-created **structured assessments** (`assessments` + `assessment_learners` pivot) the **single source of truth** for quarterly grades. Preserve the existing calculation model, table formatting, and student-dashboard UI. Disable the quarterly CSV upload as a source of truth (hide its UI, keep the code and legacy rows). Add full CRUD so admins can enter and correct assessments.

## Non-goals / Deferred

- No `Subject` model. This app is single-subject ("Mathematics 6"); keep the table title hardcoded/derived.
- Do **not** delete or modify `quarterly_assessments` data or the `QuarterlyAssessment*` controllers/routes. They stay as-is for later reuse.
- No cross-school-year historical reporting.
- No per-type configurable weights (fixed 4 × 25%).
- No conversion of legacy JSON payloads into structured assessments.

## Locked Decisions

1. **Source of truth**: structured `Assessment` + `assessment_learners.score`.
2. **Segment mapping**: `assessment_types.code` — new hidden column. Codes: `long_test`, `alternative`, `formative`. Survives renames.
3. **LT1/LT2 + ordering**: within a section + quarter, sort each type by `assessment_date` asc, tie-break `id` asc. First Long Test → `lt1`, second → `lt2`. `alternative`/`formative` render in that order as buckets.
4. **Weights**: fixed — `lt1` 25%, `lt2` 25%, AA bucket 25%, FA bucket 25% (`calculateWeightedPercentage` default `0.25`).
5. **Summary page**: Section selector + Quarter tabs (same interaction pattern as the student dashboard).
6. **Summary summary columns**: mirror the student final-grade chain (see Calculation Spec).
7. **Calculations live in one shared JS module** consumed by both the Summary and the student dashboard. Controllers return structured data only; simulation mode stays client-side.
8. **Full CRUD** for assessments: create, edit metadata, edit per-learner scores, delete.
9. **CSV**: hide the quarterly CSV nav link + "Upload new CSV" button. Keep the per-assessment "Upload scores CSV" helper on the Create form. Keep all routes/code.
10. **Validation**: Pest feature tests + factories for HTTP paths; Vitest unit tests for the shared grading module.

## Current State (baseline facts)

- Two disconnected systems: structured `Assessment` (per-learner pivot `score`) and JSON-blob `QuarterlyAssessment.assessment`.
- `AssessmentController@index/show/update/destroy` are **dead code**; only `store` and `sectionLearners` are routed. No edit/delete.
- `AssessmentSummary.vue` is broken (matches `assessment_type` snake_case, references non-existent `scores[].percent`, mixes quarters).
- Quarterly grading logic is duplicated 3×: `resources/js/Composables/useQuarterlyAssessmentCalculations.js`, `resources/js/Pages/Students/Dashboard.vue` (inline), and `app/Http/Controllers/StudentDashboardController.php`.
- `assessment_types` = Long Test / Alternative Assessment / Formative Assessment, all `percentage = 25`. `percentage` is not used in any calculation.
- `AssessmentTypeSeeder` seeds those 3 names via `updateOrCreate(['name'], ['percentage'])`.
- Only `UserFactory` exists; no assessment factories or tests.
- `Assessment` model: `fillable` title, assessment_type_id, school_year_id, quarter_id, section_id, user_id, assessment_date, perfect_score; `learners()` belongsToMany with `withPivot('score')`. No casts, no `HasFactory`.
- `assessment_learners`: unique(`assessment_id`,`learner_id`), `score` decimal(5,2) default 0; cascade deletes.
- Routes live under `/admin` in `routes/web.php:51-68` guarded by `auth, verified, EnsureUserIsAdmin`.

## Target Data Flow

```
Admin Create/Edit form ──> Assessment + pivot scores (DB)
                                   │
        ┌──────────────────────────┼───────────────────────────┐
        ▼                          ▼                           ▼
 AssessmentPageController@summary   StudentDashboardController@index
 (section + all quarters, scores)   (student's section + all quarters, own scores)
        │                          │
        ▼                          ▼
 Pages/Assessments/Summary.vue   Pages/Students/Dashboard.vue
        └──────────► resources/js/Composables/assessmentGrading.js ◄──────────┘
                     (segments, metrics, TW%, GE, final chain)
```

## Calculation Spec (must match current behavior exactly)

Reuse `resources/js/Composables/utilities.js`: `calculatePercentage`, `calculateWeightedPercentage`, `getGradeEquivalentFromPercent`, `getGradeEquivalentFromValue`, `getAdjectivalEquivalent`.

Per learner, per quarter:

1. **Segment score** = Σ scores of that segment's assessments for the learner.
2. **Segment perfect** = Σ `perfect_score` of that segment's assessments.
3. **Segment %** = `score / perfect * 100` (null if perfect ≤ 0).
4. **Segment W%** = `% × 0.25` (null if % null).
5. **TW%** = Σ of the four W% values (treat null as 0).
6. **currentGe** = `getGradeEquivalentFromPercent(TW%)` (null if TW null).
7. **previousGe** = previous quarter's `currentGe` (same computation); null for Q1.
8. **currentThird** = `currentGe × 2/3`.
9. **previousThird** = `previousGe × 1/3`.
10. **truncSource** = both thirds present ? `currentThird + previousThird` : `currentGe`.
11. **trunc** = truncate to 3 decimals.
12. **finalGe** = previousThird present ? `getGradeEquivalentFromValue(trunc)` : `currentGe`.
13. **adjectival** = `getAdjectivalEquivalent(finalGe)`.

## Work Plan (ordered tasks)

### Phase 0 — Schema, models, seeds, factories

- **T0.1** Migration `add_code_to_assessment_types_table`: `string('code')->nullable()->unique()->after('name')`.
- **T0.2** `app/Models/AssessmentType.php`: add `code` to `$fillable`; add `use HasFactory;`.
- **T0.3** `database/seeders/AssessmentTypeSeeder.php`: add codes — Long Test→`long_test`, Alternative Assessment→`alternative`, Formative Assessment→`formative`.
- **T0.4** `app/Models/Assessment.php`: add `use HasFactory;`; casts `assessment_date => 'date'`, `perfect_score => 'integer'`. (Keep pivot `score` as-is.)
- **T0.5** Add `use HasFactory;` to `GradeLevel`, `Section`, `SchoolYear`, `Quarter`, `Learner`, `Enrollment`.
- **T0.6** Factories: `AssessmentTypeFactory`, `AssessmentFactory` (with `has`/state to attach learner scores), `GradeLevelFactory`, `SectionFactory`, `SchoolYearFactory`, `QuarterFactory`, `LearnerFactory`, `EnrollmentFactory`.

### Phase 1 — Shared grading module (JS)

- **T1.1** Create `resources/js/Composables/assessmentGrading.js` (plain named exports, no composable state). Required exports:
  - `SEGMENT_WEIGHT = 0.25`
  - `mapTypeCodeToSegment(code)` → `long_test→long_test`, `alternative→aa`, `formative→fa`; fallback by type name for legacy safety.
  - `groupAssessmentsBySegment(assessments)` → `{ lt1: assessment|null, lt2: assessment|null, aa: assessment[], fa: assessment[] }`. Sort by `assessment_date` then `id`; only the first two long tests are used (extras ignored — document this).
  - `buildSegmentEntries(grouped, scoresByLearner, learnerId)` → `{ lt1:[], lt2:[], aa:[], fa:[] }` with entries shaped like the legacy JSON entries so existing UI helpers/simulation keep working: `{ key, label, fieldType:'score', value, perfectScore, assessmentId, learnerId }`. Label: long tests → assessment title (or `Score` for the first across the segment); AA/FA → assessment title.
  - `metricsForEntries(entries)` → `{ score, perfectScore, percentage, weighted }`.
  - `buildQuarterResult(grouped, scoresByLearner, learnerId, previousQuarterGe)` → `{ segments, metricsBySegment, twPercent, currentGe, currentThird, previousGe, previousThird, trunc, finalGe, adjectival }`.
  - `buildSummaryRows({ assessments, learners, previousQuarterAssessments })` → `{ segments (grouped, for headers), rows: [{ learner, cells, summary }] }` for the admin table.
- **T1.2** Do **not** break `utilities.js`; import from it only.

### Phase 2 — Server data providers

- **T2.1** `AssessmentPageController@summary`: return
  `schoolYear`, `sections` (+gradeLevel), `section` (with `enrollments.learner` for the current SY), `selectedQuarterId`, `quarters` (active SY, ordered), and `assessments` for the selected section across **all** quarters (eager-load `assessmentType:id,name,code`, `learners:id`, pivot `score`, `quarter:id,quarter`). JS computes per-quarter views. Keep `?section=` support.
- **T2.2** `AssessmentPageController@show`: include `assessmentType.code`; keep existing learner mapping.
- **T2.3** `StudentDashboardController@index`: replace the `QuarterlyAssessment` read with structured `Assessment` for the student's section + active SY across all quarters, each with `assessmentType:id,name,code`, `quarter`, and the learner's own pivot score. Pass `assessments`, `quarters`, `student`, `section`, `schoolYear`. Remove the now-unused legacy `transformAssessment`/`extractStudentRow`/`matchLearnerRow`/`collectSubHeaders`/`isSubHeaderRow`/`resolveSubheaderValues`/`buildSearchTerms` from this controller.
- **T2.4** Leave `QuarterlyAssessmentController` / `QuarterlyAssessmentPageController` and their routes untouched.

### Phase 3 — Admin Summary UI

- **T3.1** Rework `resources/js/Pages/Assessments/Summary.vue` + `resources/js/Components/Assessments/AssessmentSummary.vue`:
  - Section selector (existing) **plus quarter tabs** (1st–4th; disabled when no assessments for that quarter).
  - Columns built dynamically from `buildSummaryRows` (identity cols + per-assessment score cols + per-segment T/%/W% + TW%/GE/2-3/G/1-3/Trunc/Final GE/Adjectival). Preserve current visual styling and frozen identity columns.
  - Use the shared module for all numbers (remove inline/legacy math and dead commented blocks).
  - "All sections"/"unassigned" → show an empty state (table requires a concrete section).

### Phase 4 — Student dashboard rewiring

- **T4.1** `resources/js/Pages/Students/Dashboard.vue`: keep the template, styles, quarter selector, simulation mode, and helper components identical. Replace the inline duplicated calculation with `assessmentGrading.js`; build `lt1/lt2/aa/fa` segment entries from structured assessments (same entry shape). Derive quarter availability from quarters that have assessments.
- **T4.2** Final-grade overview now comes from the computed `finalGe`/`adjectival` (not JSON `final` segment entries). Preserve the "Tentative Score" affordance only if still meaningful; otherwise drop the tentative path (structured scores have no tentative flag).

### Phase 5 — Full CRUD

- **T5.1** Routes in `routes/web.php` (inside the admin group): add `GET /assessments/{assessment}/edit` → `assessments.edit`, `PUT/PATCH /assessments/{assessment}` → `assessments.update`, `DELETE /assessments/{assessment}` → `assessments.destroy`. Keep ordering before the `{assessment}` show route as needed.
- **T5.2** Add `app/Http/Requests/AssessmentRequest.php` with rules shared by store/update: `title` nullable string max255; `assessment_type_id` required exists; `school_year_id`, `quarter_id`, `section_id` required exists; `perfect_score` nullable int min 0; `assessment_date` required date; `learner_scores` nullable array; `learner_scores.*.learner_id` required exists learners; `learner_scores.*.score` nullable numeric min 0. Set `user_id` server-side from auth.
- **T5.3** `AssessmentController`: switch `store` to the Form Request; implement `update` (metadata + `assessment_date` + `perfect_score` + `sync()` of `learner_scores`); implement `destroy` (delete; pivot cascades). Keep JSON/redirect behavior consistent (`wantsJson()` vs redirect to `assessments.index`).
- **T5.4** `AssessmentPageController@edit`: return `assessment` (+ `assessmentType.code`, learner scores keyed by learner id) plus the same option data as `create()`.
- **T5.5** Extract `resources/js/Components/Assessments/AssessmentForm.vue` from `Pages/Assessments/Create.vue`; use it in `Create.vue` and a new `Pages/Assessments/Edit.vue` (prefill metadata + per-learner scores; submit PUT). Keep the per-assessment "Upload scores CSV" helper.
- **T5.6** Add Edit/Delete actions to `Components/Assessments/AssessmentDetail.vue` (and optionally row actions in `AssessmentMainContent.vue`). Delete uses `router.delete` + `confirm`.
- **T5.7** Update `Pages/Assessments/Show.vue`/`AssessmentDetail.vue` if the payload shape changes (score display).

### Phase 6 — Hide quarterly CSV

- **T6.1** `resources/js/Layouts/MainAuthLayout.vue`: hide the "Quarterly CSV" nav `Link` (and its `quarterlyPaths`/`isQuarterlyActive` usage). Keep the code (comment or feature flag) for later reuse.
- **T6.2** `resources/js/Pages/QuarterlyAssessments/Index.vue`: hide the "Upload new CSV" `Link`. Leave the per-assessment "Upload scores CSV" button on the Create form untouched.

### Phase 7 — Validation & cleanup

- **T7.1** Pest feature tests (`tests/Feature/Assessments/`): admin-only access (403 for non-admin), `store` creates assessment + pivot scores, `update` edits metadata/scores, `destroy` removes assessment and pivot rows, `section-learners` response, `summary` page props, student dashboard props from structured data.
- **T7.2** Vitest unit tests (`resources/js/Composables/__tests__/assessmentGrading.spec.js`): LT1/LT2 ordering by date; AA/FA bucket sums; %/W%/TW%; GE mapping; final chain incl. Q1 fallback and previous-quarter 1/3; missing/zero perfect scores; empty segments.
- **T7.3** Remove the now-dead inline calculation copy in `Students/Dashboard.vue` and any dead commented blocks in `AssessmentSummary.vue`.
- **T7.4** Run: `php artisan test`, `npx vitest run`, `vendor/bin/pint`, `npm run build`.

## Migration / Rollout

- Single additive migration (assessment_types.code) + seeder update. Re-run `AssessmentTypeSeeder` to backfill codes on the 3 existing rows.
- No destructive changes; `quarterly_assessments` untouched. Rollback = revert code + drop the added column.
- Until Phase 6 hides the nav, the legacy quarterly pages remain reachable but are no longer shown.

## Edge Cases & Failure Modes

- **< 2 long tests** or **> 2 long tests**: LT1 = first by date, LT2 = second (or empty); extras ignored and flagged in the plan's test coverage.
- **Missing/null/0 `perfect_score`**: percentage/weighted null; contributes 0 to TW%.
- **Learner with no score**: treated as 0 / dash; row still renders.
- **Previous quarter empty**: `previousGe` null → final = currentGe, no 1/3 term.
- **Section filter `all`/`unassigned`**: Summary shows empty state.
- **Pivot sync**: edit form submits the full learner list so `sync()` is authoritative; document that partial submissions drop unlisted learners.
- **Authorization**: per-record ownership is not enforced today (admin-only middleware); keep as-is unless requested.
- **Enrollment scope**: use the section's enrollments for the active school year; define whether inactive enrollments appear (match `section-learners`, active only) in the controller and note it in tests.

## Validation Summary

- Calculations verified by Vitest against the spec above; both UI surfaces import the same module so they cannot drift.
- HTTP/write paths verified by Pest feature tests with factories.
- Manual smoke: create → edit (metadata + scores) → view Summary (section + quarter tabs) → delete; student dashboard shows the same numbers as the Summary for that learner.

## Affected Files (reference)

Create: migration for `code`; `app/Http/Requests/AssessmentRequest.php`; `resources/js/Composables/assessmentGrading.js`; `resources/js/Components/Assessments/AssessmentForm.vue`; `resources/js/Pages/Assessments/Edit.vue`; Vitest config + spec; Pest tests + factories.

Modify: `AssessmentTypeSeeder`, `Assessment`, `AssessmentType`, `GradeLevel`, `Section`, `SchoolYear`, `Quarter`, `Learner`, `Enrollment` models; `AssessmentController`, `AssessmentPageController`, `StudentDashboardController`; `routes/web.php`; `Pages/Assessments/{Summary,Create,Show}.vue`; `Components/Assessments/{AssessmentSummary,AssessmentDetail,AssessmentMainContent}.vue`; `Pages/Students/Dashboard.vue`; `Layouts/MainAuthLayout.vue`; `Pages/QuarterlyAssessments/Index.vue`; `package.json` (Vitest).

Leave unchanged: `QuarterlyAssessment*` controllers, `QuarterlyAssessment` model, `quarterly_assessments` migration/data, `QuarterlyAssessments/{Show,Upload}.vue`, `useQuarterlyAssessmentCalculations.js`.
