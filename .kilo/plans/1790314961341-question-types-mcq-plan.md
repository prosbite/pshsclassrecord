# Question Types & Multiple-Choice Options (Admin Bank)

Follow-up to `.kilo/plans/1790310999010-exercise-remediation-bank-plan.md`. This iteration upgrades the **admin question construction** so a question can be `multiple_choice` (options + one correct answer) or `text` (current free-response), and adds first-class math authoring/rendering (KaTeX + MathLive) so equations no longer require screenshots. It also freezes options into exercise-session snapshots so the existing remediation sessions keep working. The **student-facing remediation page/tag is deferred** to a following plan; this plan only ensures the data model and admin UI support it later.

## Locked Decisions

1. **Types**: `type ∈ {multiple_choice, text}`. Existing questions migrate to `text` (keep `answer_key`). True/False and identification are modeled as MCQ-with-2-options / text; no dedicated types.
2. **Single correct answer** per MCQ (no multi-answer). Exactly one option is `is_correct`.
3. **Options are text-only** (no per-option image). Question-level `image_path` remains the prompt media.
4. **Storage is normalized** (no JSON): `question_options` for the bank, `exercise_session_question_options` for frozen snapshots, both keyed by `position` and joined to their source by nullable FKs.
5. **Snapshots freeze type + options + correctness** at session creation/reconcile time. Editing/deleting bank options never changes past sessions.
6. **Admin-only**; no student/teacher UI. Do not touch `assessmentGrading.js`, the grade chain, `QuarterlyAssessment*`, or the assessment pool/session routes beyond snapshotting options.
7. **`answer_key`** stays for `text` questions. For `multiple_choice` it is forced `null`; the correct answer is the marked option.
8. **Correct answers are admin-visible only.** Future student endpoints must select only option `id`/`label` (never `is_correct`); note this in code comments where serialized.
9. **Math is stored as inline delimited LaTeX** inside the existing string fields (`prompt_text`, option `label`, `answer_key`). No schema change, and snapshots freeze the raw string automatically.
10. **Render with KaTeX, author with MathLive**: `katex` (+ `contrib/auto-render`) is the display renderer; `mathlive`'s interactive `<math-field>` is the equation input, lazy-loaded only when the author opens the editor.
11. **Delimiters**: `\(...\)` inline, `\[...\]` and `$$...$$` block. A single `$...$` is intentionally **not** recognized, so peso/currency amounts never mis-render as math. The MathLive insert button wraps output in `\(...\)`, so authors never type delimiters.

## Data Model

All migrations additive, continuing the `2026_09_25_*` series.

- `question_options`: `id`, `question_id` FK → questions **cascadeOnDelete**, `position` (unsigned int default 0), `label` (string), `is_correct` (boolean default false), timestamps.
- `questions.type`: string default `text` (not null), added after `id`.
- `exercise_session_questions.type`: string default `text` (not null), added after `exercise_session_id`.
- `exercise_session_question_options`: `id`, `exercise_session_question_id` FK → exercise_session_questions **cascadeOnDelete**, `source_option_id` FK → question_options **nullOnDelete**, `position`, `label`, `is_correct` (boolean), timestamps.

Suggested migrations (one concern each):
- `..._000012_create_question_options_table`
- `..._000013_add_type_to_questions_and_exercise_session_questions`
- `..._000014_create_exercise_session_question_options_table`

Backfill: the `type` column defaults mean existing `questions` and `exercise_session_questions` rows become `text` with no options — no data migration script needed.

## Models

- `Question`: add `type` to `$fillable`; `options(): HasMany` ordered by `position` (`QuestionOption`).
- `QuestionOption` (new): `$fillable = [question_id, position, label, is_correct]`; casts `is_correct => boolean`; `belongsTo(Question)`; `HasFactory`.
- `ExerciseSessionQuestion`: add `type` to `$fillable`; `options(): HasMany` (`ExerciseSessionQuestionOption`) ordered by `position`.
- `ExerciseSessionQuestionOption` (new): `$fillable = [exercise_session_question_id, source_option_id, position, label, is_correct]`; casts `is_correct => boolean`; `belongsTo` sessionQuestion + sourceOption; `HasFactory`.
- `Question`'s existing `image_url` append is unchanged. Do **not** append options to `ExerciseSessionQuestion` automatically without loading them (avoid N+1); controllers eager-load explicitly.

## Requests / Validation

- `QuestionRequest`:
  - add `type` → `required|in:multiple_choice,text` (default `text` when omitted in the form).
  - `options` → `required_if:type,multiple_choice|array|min:2|max:10`; `options.*.id` → `nullable|integer`; `options.*.label` → `required|string|max:255`; `options.*.is_correct` → `boolean`.
  - `after()`: when `type=multiple_choice`, exactly one `options.*.is_correct` must be truthy (add error `options`); keep the existing "prompt text or image" check unchanged.
  - `answer_key` remains `nullable|string` (ignored/forced null for MCQ in the controller).

## Controllers

- `QuestionController@store/update`:
  - persist `type`; when `multiple_choice`, ignore `answer_key` (store null); when `text`, delete existing options.
  - sync options in array order: match existing by `options.*.id` (must belong to the question), update; create new; delete options whose ids are absent. Set `position` from array order.
  - `store`/`update` continue to handle the prompt image exactly as today.
- `QuestionController@index`: `with('topic')->withCount(['questionnaires','options'])`; add `?type=` filter (optional) and pass `type` in props.
- `QuestionController@edit`: `$question->load('topic', 'options')`.
- `QuestionnaireController@edit`: `availableQuestions`/attached `questions` now include `type` (for badges); no structural change.
- `ExerciseSessionController@show`: load `sessionQuestions` with `options` (e.g. `$session->sessionQuestions()->with('options')->get()`).

## Snapshot Services

- `AssessmentRemediationService::syncSessionQuestionnaires`:
  - eager-load `questions.options` (ordered by `position`).
  - include `type` in the snapshot create/update payload.
  - after create/update of each `exercise_session_questions` row, reconcile its options by `source_option_id`: update label/is_correct/position in place, insert rows for new options, delete rows no longer present. De-dup questions across questionnaires as today.
  - Preserve existing score/`graded_at` on the snapshot row (already handled by update-in-place).
- `ExerciseSessionService::snapshotQuestion`: include `type`; document that options are reconciled by the session service/sync path (child rows, not part of the array payload).
- Do not delete `question_options` rows' snapshot counterparts on bank edits; `source_option_id` nulls on bank deletion while the frozen label/correctness survives.

## Vue (Admin)

- `Components/Exercises/QuestionForm.vue`:
  - add a **Type** select (`multiple_choice` / `text`), defaulting to the loaded question's type or `text`.
  - when `text`: keep the `answer_key` field.
  - when `multiple_choice`: hide `answer_key`; render an options editor — rows of `label` inputs with a radio for "correct", per-row remove, and an "Add option" button; enforce ≥2 rows client-side. Include `options` in the `useForm` payload as `{ id?, label, is_correct }[]`.
- `Pages/Questions/Index.vue`: add a **Type** column/badge and an **Options** count; label MCQ rows clearly; keep the topic filter.
- `Pages/Questionnaires/Edit.vue`: question picker labels unchanged (prompt text), optionally prefix MCQ with a small badge.
- `Pages/ExerciseSessions/Show.vue`: in the frozen-questions table, render `question.type`; for MCQ list `question.options` with the correct one highlighted and an "Answer key" line derived from the correct option; for text keep the existing `answer_key` line. Score input logic is unchanged.
- `Create.vue`/`Edit.vue` question pages need no prop changes beyond what the controller already passes.

## Math Authoring & Rendering (KaTeX + MathLive)

Dependencies (add to `package.json` `dependencies`): `katex`, `mathlive`.

Build/config:
- Import `katex/dist/katex.min.css` once in `resources/js/app.js` (Vite bundles its fonts).
- `vite.config.js`: register `math-field` as a custom element on the Vue plugin, e.g. `vue({ template: { compilerOptions: { isCustomElement: (tag) => tag === 'math-field' } } })`, so Vue doesn't try to resolve it as a component.

Shared components (new, under `resources/js/Components/Exercises/`):
- `MathText.vue`: props `content` (String), `tag` (default `span`). On mount and on content change: set `el.textContent = content`, then run `renderMathInElement(el, { delimiters: [{left:'\\(',right:'\\)',display:false},{left:'\\[',right:'\\]',display:true},{left:'$$',right:'$$',display:true}], throwOnError:false, trust:false, maxExpand:1000 })` inside try/catch. Never `v-html` (text stays escaped / XSS-safe); malformed LaTeX falls back to raw text.
- `MathInsertButton.vue` (reusing the existing `Modal.vue` shell): opens a modal with a `<math-field>`, and on confirm inserts the field's LaTeX wrapped in `\(...\)` at the caret of the bound textarea/input. Lazy-load MathLive via `await import('mathlive')` on first open so the initial bundle is unaffected.

Form integration (`Components/Exercises/QuestionForm.vue`):
- Prompt textarea: textarea + "Insert equation" button + live `<MathText>` preview.
- Each MCQ option `label`: input + insert button; preview via `<MathText>` in the editor and read-only lists.
- `text` `answer_key`: same textarea + insert + preview treatment.
- Prompt image upload is untouched; math and prompt images are independent and may coexist.

Read-only rendering (wrap existing text output in `<MathText>`):
- `Pages/Questions/Index.vue` (prompt column), `Pages/Questionnaires/Edit.vue` (question labels/lists), `Pages/ExerciseSessions/Show.vue` (frozen prompt, options, `answer_key`).
- Native `<select>`/`<option>` pickers stay as-is and show raw LaTeX (options can't render HTML); rendered math appears only in lists, previews, and detail views.
- The deferred student remediation views reuse the same component.

Guardrails:
- KaTeX stays `trust: false` with a bounded `maxExpand`; no `\href`/`\includegraphics`/`\htmlClass`.
- Rendering is presentational only; snapshot services, scoring, and stored strings are untouched.
- If MathLive fonts/assets misresolve under Vite, fall back to the plain textarea + delimiters (the insert button still works), so authoring is never blocked.

## Factories & Tests

- `QuestionFactory`: add `'type' => 'text'`. New `QuestionOptionFactory` (`question_id => Question::factory()`, `label`, `is_correct => false`, `position => 0`).
- `ExerciseSessionFactory::withQuestions`: continue creating `text` questions; add a `withMcq` state that creates options + snapshots them (optional but useful).
- Extend `tests/Feature/Exercises/` (Pest, MySQL DB override):
  - question create with `multiple_choice` + 2 options and one correct persists type/options/positions; `answer_key` stored null.
  - validation: `type` required/valid; MCQ needs ≥2 options; exactly one correct (0 and 2 both rejected); text question keeps `answer_key` and deletes options on switch.
  - update replaces/reorders options; switching to `text` clears options; switching `text`→MCQ adds them.
  - question index exposes `type` and `options_count`.
  - session creation snapshots `type` + options with `is_correct`; editing bank option labels/correctness afterwards does NOT change the frozen snapshot; deleting a bank option nulls `source_option_id` but keeps the frozen label/correctness.
  - admin `exercise-sessions.show` exposes frozen `type` + options (and keeps answer_key for text).
  - existing text-question tests remain green.

## Out of Scope (next plan)

- Student-facing remediation page + dashboard "Remediation available" tag/link.
- Student answer capture (selected option / free text), status flow `assigned → submitted → completed`, auto-grading, feedback.
- Multi-answer MCQ, per-option images, true/false/identification types.
- MathJax / chemistry / advanced LaTeX macro packages; rich-text WYSIWYG editors (TipTap/CKEditor). KaTeX + MathLive cover K-12 algebra/geometry/trig notation.
- Notifications.

Forward-compatibility requirements this plan must satisfy for that next plan: options frozen with stable `id` + `source_option_id`; `is_correct` available server-side but never leaked to student props; snapshot rows keep `score`/`graded_at` semantics.

## Validation

- `php artisan migrate` + `php artisan db:seed --class=SettingSeeder` (dev DB).
- `php artisan test tests/Feature/Exercises` with `DB_CONNECTION=mysql DB_DATABASE=class_record_test` (no `pdo_sqlite` on this host).
- `php vendor/bin/pint` on changed PHP; `npm run build`.
- Manual smoke: create an MCQ with 3 options and mark B correct → attach the question to a questionnaire → create a session → confirm the session freezes the type/options and marks B → edit B's label/correctness in the bank → confirm the session snapshot is unchanged.
- Math smoke: insert `\frac{x}{2}=4` via the MathLive button into a prompt, an option label, and an answer key → confirm it renders in the form preview, Questions index, and the session page → confirm `₱5 and ₱10` stays literal text (no single-`$` parsing).

## Risks

- **Answer leakage**: keep `is_correct` out of any future student serialization; admin-only controllers may return it.
- **Snapshot churn**: reconcile options by `source_option_id` (update in place) rather than delete+recreate, so future student answers can reference stable snapshot option ids.
- **Existing sessions**: default `type=text` and absence of options must render exactly as today.
- **Ordering**: option order in the form (array order) becomes `position`; snapshot must preserve it.
- **MathLive weight/assets**: MathLive is large and needs its fonts; lazy-import it on first editor open and keep the plain-textarea fallback if assets misresolve.
- **KaTeX safety/DoS**: keep `trust:false` and `maxExpand` bounded; malformed input must fall back to raw text, not throw.
