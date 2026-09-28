# Exercise / Remediation Bank for Failing Assessments

Third plan in the assessments series. Builds on `.kilo/plans/1790177295572-assessments-revamp-plan.md` (structured assessments as source of truth) and `.kilo/plans/1790178668295-tentative-scores-plan.md` (per-student tentative flag). Do not re-plan those; reuse their models, factories, and test style.

## Goal

Let an admin build a reusable **topic → questionnaire → question** bank, attach a chosen set of questionnaires to an assessment, and — for learners who fail that assessment — create **exercise sessions** that record which questionnaires were given and the per-question marks the teacher verifies from the student's paper work. Remediation never changes grades; it is a separate record.

## Locked Decisions

1. **Admin-only** in this iteration. No student or teacher UI. No grade/`assessmentGrading.js` changes.
2. **Bank hierarchy**: a questionnaire belongs to exactly one topic (`questionnaires.topic_id`, required). A question belongs to one questionnaire. Topics are an organizing/suggestion aid; they never decide an assessment's questionnaire pool.
3. **Assessment pool is explicit**: `assessment_topic` (display tags) + `assessment_questionnaire` (authoritative selected set). Choosing a topic bulk-adds its questionnaires; the admin then removes any.
4. **Question content**: optional `prompt_text` and/or `image_path` (at least one required), integer `points` (default 1), optional `answer_key` (grading aid only). No options table, no auto-grading.
5. **Assignments**: the assessment's questionnaire pool is what gets disseminated to failing learners. Each failing learner gets one **exercise session**; a session's questionnaires are a customizable subset/override (many-to-many), so an individual student can be given a different set.
6. **No strict completion**: blank score = not attempted. The teacher verifies the work and enters optional per-question marks. Totals are informational over attempted questions only. No all-questions requirement.
7. **History is frozen per session**: at session creation, served questions are snapshotted into `exercise_session_questions` (prompt, image, points, answer key) with `source_question_id`/`source_questionnaire_id`. Editing the bank never changes past sessions. **No JSON** — the snapshot is normalized so each score attaches to a stable row and remains queryable.
8. **Failing rule**: a learner has a score row on the assessment and `score / perfect_score * 100 < passing_threshold`. Threshold is a **key-value setting** (`passed` via a new `settings` table + admin Settings page), default `75`. Tentative-score rows appear with the tentative marker but are **not auto-selected**; the admin can manually add any learner from the section.
9. **Sessions surface**: per-assessment remediation page only (`/admin/assessments/{assessment}/remediation`), reached from the Assessment Show page. No global session list in this iteration.
10. **Deleting a bank item never destroys session history**: session snapshots use `nullOnDelete` source references and hold their own content.

## Data Model (new tables)

All migrations additive, no changes to existing tables.

- `settings`: `id`, `key` (unique), `value` (text, nullable), `group` (nullable), timestamps.
- `topics`: `id`, `name` (unique), `description` (nullable text), timestamps.
- `questionnaires`: `id`, `topic_id` FK → topics **restrictOnDelete** (empty a topic before deleting it), `title`, `instructions` (nullable text), `position` (unsigned int, default 0), timestamps.
- `questions`: `id`, `questionnaire_id` FK → questionnaires **cascadeOnDelete**, `position`, `prompt_text` (nullable text), `image_path` (nullable string), `points` (unsigned int, default 1), `answer_key` (nullable text), timestamps.
- `assessment_topic`: `id`, `assessment_id` FK cascade, `topic_id` FK cascade, timestamps, unique(`assessment_id`,`topic_id`).
- `assessment_questionnaire`: `id`, `assessment_id` FK cascade, `questionnaire_id` FK **cascadeOnDelete**, `position`, timestamps, unique(`assessment_id`,`questionnaire_id`).
- `exercise_sessions`: `id`, `assessment_id` FK cascade, `learner_id` FK cascade, `created_by` FK → users nullOnDelete, `status` string default `assigned` (`assigned|completed`), `remark` (nullable text), `completed_at` (nullable timestamp), timestamps, unique(`assessment_id`,`learner_id`).
- `exercise_session_questionnaire`: `id`, `exercise_session_id` FK cascade, `questionnaire_id` FK **nullOnDelete**, `position`, timestamps, unique(`exercise_session_id`,`questionnaire_id`).
- `exercise_session_questions` (frozen snapshot + score carrier): `id`, `exercise_session_id` FK cascade, `source_question_id` FK → questions nullOnDelete, `source_questionnaire_id` FK → questionnaires nullOnDelete, `position`, `prompt_text` (nullable), `image_path` (nullable), `points` (unsigned int), `answer_key` (nullable), `score` (decimal(5,2), nullable), `graded_at` (nullable timestamp), timestamps.

Note: Eloquent serializes loaded relation keys as **snake_case** (`exercise_session_questionnaires`, not camelCase). Keep that in mind for Inertia props.

## Models and Helpers

- `Setting`: static `get(string $key, $default = null)` / `set(string $key, $value)` using `Cache::rememberForever` + `Cache::forget`; `passingThreshold(): float` returns `(float) static::get('passing_threshold', 75)`.
- `Topic` hasMany `questionnaires`.
- `Questionnaire` belongsTo `topic`, hasMany `questions` (ordered by `position`).
- `Question` belongsTo `questionnaire`; accessor `image_url` = `Storage::disk('public')->url($this->image_path)` when set.
- `Assessment` additions: `topics()` belongsToMany with `withTimestamps`; `questionnaires()` belongsToMany (table `assessment_questionnaire`) `withPivot('position')->withTimestamps()->orderByPivot('position')`; `exerciseSessions()` hasMany.
- `Learner` addition: `exerciseSessions()` hasMany.
- `ExerciseSession` belongsTo `assessment`, `learner`, `creator` (User, FK `created_by`); belongsToMany `questionnaires` through `exercise_session_questionnaire` with pivot `position`; hasMany `sessionQuestions` (`ExerciseSessionQuestion`, ordered by position); computed accessors `attemptedCount`, `totalScore`, `maxScore`, `percent` (total/max*100, null when max 0).
- `ExerciseSessionQuestion` belongsTo `session`, `sourceQuestion`, `sourceQuestionnaire`; casts `score => decimal:2`, `graded_at => datetime`.

## Services

- `app/Services/AssessmentRemediationService.php`
  - `failingLearners(Assessment $assessment): Collection` → active current-school-year enrollments of the assessment's section, left-joined to the assessment's learner pivots; returns `{ learner, score, tentative, percent, is_failing }`. Percent null when `perfect_score <= 0` or no score; `is_failing` only when percent is non-null and `< Setting::passingThreshold()`.
  - `syncSessionQuestionnaires(ExerciseSession $session, array $questionnaireIds): void` → keep existing snapshot rows for retained sources (preserving scores), append snapshot rows for newly added questionnaires, delete snapshot rows whose `source_questionnaire_id` is removed, then rewrite the session↔questionnaire pivot positions.
- `app/Services/ExerciseSessionService.php`
  - `createFor(Assessment $assessment, Learner $learner, array $questionnaireIds, ?int $userId): ExerciseSession` → `firstOrCreate` session (one per learner/assessment), then `syncSessionQuestionnaires`.
  - `snapshotQuestion(Question $question, int $sessionId, int $position): array` → copy content + source ids (no score).
  - `syncScores(ExerciseSession $session, array $entries): void` → each `{ session_question_id, score }`; blank/omitted score stores `null`; set/clear `graded_at` accordingly.

## Image Storage

- Store uploads on the `public` disk (`storage/app/public/questions/`), random filename, extension from the mime.
- Validation: `image` `nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096`.
- On question update, delete the replaced file. On question delete, **do not** delete the file if any `exercise_session_questions.image_path` still references it (snapshots share the path).
- Implementation must run `php artisan storage:link` (mutating command; the implementing agent runs it), and confirm `public/storage` exists.

## Routes (admin group, `routes/web.php`)

Register the nested remediation routes **before** the `assessments/{assessment}` show wildcard.

```
GET  /settings                              settings.edit
PUT  /settings                              settings.update
Route::resource('topics', TopicController::class)->except(['show']);
Route::resource('questionnaires', QuestionnaireController::class)->except(['show']);
Route::resource('questions', QuestionController::class)->except(['show']);
GET    /assessments/{assessment}/remediation                    assessments.remediation
POST   /assessments/{assessment}/remediation/sessions           assessments.remediation.sessions.store
GET    /exercise-sessions/{exerciseSession}                     exercise-sessions.show
PUT    /exercise-sessions/{exerciseSession}                     exercise-sessions.update
DELETE /exercise-sessions/{exerciseSession}                     exercise-sessions.destroy
```

## Work Plan (ordered tasks)

### Phase 0 — Schema, models, seed
- **T0.1** Migrations for `settings`, `topics`, `questionnaires`, `questions`, `assessment_topic`, `assessment_questionnaire`, `exercise_sessions`, `exercise_session_questionnaire`, `exercise_session_questions` (use one timestamped migration per table or a few grouped, matching existing naming).
- **T0.2** Models as specified above (`HasFactory` where factories are added).
- **T0.3** `SettingSeeder` (`passing_threshold` = `75`); register in `DatabaseSeeder`.
- **T0.4** Factories: `TopicFactory`, `QuestionnaireFactory`, `QuestionFactory`, `ExerciseSessionFactory` (states for `completed`, `withQuestions`).

### Phase 1 — Bank CRUD backend
- **T1.1** `TopicController@index/create/store/edit/update/destroy` with `TopicRequest` (`name` required unique, `description` nullable). Topic destroy returns a validation error when questionnaires exist (FK restrict).
- **T1.2** `QuestionnaireController@index/create/store/edit/update/destroy` with `QuestionnaireRequest` (`topic_id` required exists, `title` required, `instructions` nullable, `position` nullable int). Index supports `?topic=`.
- **T1.3** `QuestionController@index/create/store/edit/update/destroy` with `QuestionRequest` (`questionnaire_id` required exists, `prompt_text` nullable required_without image, `image` file rules above, `points` required int min 1, `answer_key` nullable, `position` nullable). Index supports `?questionnaire=`. Enforce "text or image" with a validator.
- **T1.4** Do not touch `QuarterlyAssessment*` or `assessmentGrading.js`.

### Phase 2 — Settings
- **T2.1** `SettingsController@edit/update` (`Settings/Edit.vue`); validate `passing_threshold` required numeric min 0 max 100. Persist via `Setting::set`.
- **T2.2** Expose the current threshold on the settings page.

### Phase 3 — Assessment pool
- **T3.1** `AssessmentRequest`: add `topic_ids` (`nullable|array`), `topic_ids.*` (`integer`), `questionnaire_ids` (`nullable|array`), `questionnaire_ids.*` (`integer`); validate set-wise with one `whereIn` per relation (mirror the existing `after()` learner-id pattern).
- **T3.2** `AssessmentController@store/update`: `sync()` `topics()` and `questionnaires()` (override name on the pivot to store `position` from array order). Keep existing learner `sync`/`syncWithoutDetaching` behavior untouched.
- **T3.3** `AssessmentPageController::formOptions()` also returns `topics` (with id/name) and `questionnaires` (id, title, topic_id, ordered). `edit()` also returns `selectedTopicIds` and `selectedQuestionnaireIds`.
- **T3.4** `AssessmentForm.vue`: add an "Exercise questionnaires" section — topic multi-select chips (selecting a topic appends all its questionnaires to the selected list), a selected-questionnaire list grouped by topic with remove buttons, and an "add questionnaire" select. Include `topic_ids` and `questionnaire_ids` in `useForm` and submit. Edit mode prefills from props.

### Phase 4 — Remediation page and sessions
- **T4.1** `AssessmentRemediationController@show(Assessment $assessment)`: eager-load type/section/quarter; pass `assessment`, `poolTopics`, `poolQuestionnaires`, `bankTopics`/`bankQuestionnaires` (for adding), `failingLearners` (from service), `threshold`, and existing `sessions` (with learner, session questionnaires, counts/totals).
- **T4.2** `AssessmentRemediationController@storeSessions`: validate `learner_ids` (`required|array`, `*` integer exists learners) and `questionnaire_ids` (`required|array|min:1`, `*` exists questionnaires). For each learner, `ExerciseSessionService::createFor` with the selected questionnaires; skip learners who already have a session and report a count. Redirect back with a status message.
- **T4.3** `ExerciseSessionController@show(ExerciseSession $session)`: scoring page props — session with learner, assessment, `questionnaires` (pivot), `sessionQuestions` (content, points, score, graded_at), computed totals, and the bank questionnaires available to add.
- **T4.4** `ExerciseSessionController@update(ExerciseSessionUpdateRequest)`: accept `questionnaire_ids` (nullable array), `scores` (nullable array of `{ session_question_id, score }`), `remark` (nullable), `status` (`assigned|completed`). Call `syncSessionQuestionnaires` then `syncScores`; `status=completed` sets `completed_at`. Redirect back.
- **T4.5** `ExerciseSessionController@destroy`: delete session (snapshot rows cascade). Redirect back to the assessment's remediation page.
- **T4.6** `AssessmentDetail.vue`: add a "Remediation" link to `assessments.remediation`.
- **T4.7** Vue pages: `Pages/Assessments/Remediation.vue` (pool, failing list with tentative marker and manual add, create sessions, session list) and `Pages/ExerciseSessions/Show.vue` (score rows, remove snapshot questions, add/remove questionnaires, remark, mark completed).

### Phase 5 — Bank UI
- **T5.1** `Components/Exercises/QuestionForm.vue` (shared create/edit; image upload with preview, points, answer key, position).
- **T5.2** `Pages/Topics/{Index,Create,Edit}.vue`; topic Edit lists its questionnaires with add/edit/remove links.
- **T5.3** `Pages/Questionnaires/{Index,Create,Edit}.vue`; questionnaire Edit lists its questions with add/edit/remove and links to `QuestionForm`.
- **T5.4** `Pages/Questions/{Index,Create,Edit}.vue` (filters by questionnaire).
- **T5.5** `Pages/Settings/Edit.vue` (threshold form).
- **T5.6** `MainAuthLayout.vue`: add nav links Topics, Questionnaires, Questions, Settings with active-path computed like the existing links (use heroicons already available).

### Phase 6 — Tests and validation
- **T6.1** Pest (`tests/Feature/Exercises/`): non-admins forbidden on every new route; topic/questionnaire/question CRUD; question requires text or image; assessment store/update persists `topic_ids` + `questionnaire_ids`; settings update persists and changes failing detection; remediation page flags failing learners at 75 and respects a changed threshold; tentative rows are flagged and not auto-selected; manual-add learner allowed; session store snapshots questions and skips duplicates; session update saves nullable scores, computes totals over attempted only, removes a questionnaire's snapshot rows, and stores remark/status; session delete cascades snapshot rows.
- **T6.2** No Vitest needed (all new logic is server-side; `assessmentGrading.js` is untouched).
- **T6.3** Run: `php artisan test tests/Feature/Exercises` (this machine has no `pdo_sqlite`; use `DB_CONNECTION=mysql DB_DATABASE=class_record_test`), `php vendor/bin/pint` on changed PHP, `npm run build`, `php artisan storage:link`.

## Edge Cases and Failure Modes

- **`perfect_score` = 0 or missing score row**: percent null → not auto-flagged; manual add still available.
- **Threshold changes**: failing list recomputes on next page load; stored sessions are unaffected.
- **Questionnaire with zero questions**: session is created with no snapshot rows; percent null; UI shows an empty state.
- **Adding a questionnaire to an existing session**: only its questions are snapshotted; already-scored rows are preserved.
- **Removing a questionnaire / snapshot question**: deletes its snapshot rows and their scores (confirm in the UI). Scores for retained questions survive because they key on `source_question_id`.
- **Duplicate session creation**: skipped, not duplicated (unique `assessment_id, learner_id`).
- **Deleting a bank item**: topic with questionnaires is blocked; deleting a questionnaire removes it from assessment pools and nulls snapshot source refs but leaves snapshot content/scores intact; deleting a question nulls `source_question_id` but leaves snapshot rows/scores.
- **Deleting an assessment**: cascades its sessions and snapshots (deliberate; grades never depended on them).
- **Image shared by snapshots**: never delete the underlying file if a snapshot still references the path.
- **Paper workflow**: no answer capture, no auto-grading, no enforcement that every question is scored.

## Out of Scope

- Student-facing or teacher-facing remediation UI (admin-only now; teacher role later).
- Any effect on quarterly grades, `assessmentGrading.js`, or the grade chain.
- Notifications/emails, due-date reminders.
- Numbered retakes/attempts, auto-grading, question options.
- A global session list across assessments.
- Per-assessment threshold overrides (single app-wide setting).
- Bulk import of topics/questionnaires/questions.

## Validation Summary

- Schema/persistence, access control, failing detection, threshold setting, snapshot freezing, scoring, and non-destructive session edits are covered by Pest feature tests with factories.
- Manual smoke: create a topic → add a questionnaire → add text/image questions → attach the topic's questionnaires to an assessment (remove one) → open Remediation → confirm failing learners are flagged at 75%, create sessions for two learners, give one learner a different questionnaire subset, enter partial per-question scores and a remark, mark completed; then edit a bank question and confirm the session's snapshot and scores are unchanged.

## Assumption

Remediation is advisory/tracked only: nothing here feeds the grade. If grade impact is wanted later, it becomes a separate plan because it touches `assessmentGrading.js` and the final-grade chain.
