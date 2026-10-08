# Full Exercise Submissions Page (awaiting marking + not answered)

Add a dedicated admin page that expands the dashboard's "Awaiting marking" preview into
a full, paginated, searchable list, with a second tab listing learners whose exercise
sessions are still `assigned` (given but not answered/submitted yet).

## Locked decisions (from planning Q&A)

1. **Page location:** under the existing Exercises area — `GET /admin/exercises/submissions`
   (`exercises.submissions.index`). No new sidebar nav item; it inherits the existing
   `Exercises` nav highlight (`currentPath.startsWith('/admin/exercises')`). Reached via a
   link from the dashboard "Awaiting marking" card.
2. **Tab 1 "Awaiting marking"** = `ExerciseSession.status === 'submitted'` (newest submitted
   first). Same meaning as the dashboard preview.
3. **Tab 2 "Did not answer yet"** = `ExerciseSession.status === 'assigned'` (given but not
   submitted), newest created first. One row per learner/assessment session (the unique
   `(assessment_id, learner_id)` constraint guarantees one row).
4. **Search:** one search box above the tabs matching the learner's `first_name` OR
   `last_name` (LIKE). The term is preserved when switching tabs and when paginating.
5. **Pagination:** server-side, 25 per page, query string preserved (`withQueryString`).
6. **Scope:** current school year only, via `assessment.school_year_id = SchoolYear::current()->id`
   (same scoping the dashboard already uses).

## Out of scope

- Changing the dashboard preview itself (it stays capped at 8) except for adding the link.
- Editing/marking sessions from the new list beyond linking to the existing
  `exercise-sessions.show` page.
- Section/assessment/kind filters, sorting controls, CSV export.
- Any change to student-facing pages.

## Routes

In `routes/web.php`, inside the existing admin group, add immediately after the
`/exercises/create` route (order is safe: there is no competing `GET /exercises/{x}`):

```php
Route::get('/exercises/submissions', [ExercisePageController::class, 'submissions'])
    ->name('exercises.submissions.index');
```

## Backend

### `app/Http/Controllers/ExercisePageController.php` (add `submissions()`)

Reuse the existing imports (`Assessment`, `ExerciseSession`, `SchoolYear`, `Inertia`,
`Illuminate\Http\Request`). Add one public method with a private mapping helper:

```php
public function submissions(Request $request)
{
    $schoolYear = SchoolYear::current();
    $tab = $request->query('tab') === 'assigned' ? 'assigned' : 'submitted';
    $search = trim((string) $request->query('search', ''));

    $base = ExerciseSession::query()
        ->when($schoolYear, fn ($q) => $q->whereHas(
            'assessment',
            fn ($a) => $a->where('school_year_id', $schoolYear->id)
        ));

    $counts = [
        'submitted' => (clone $base)->where('status', 'submitted')->count(),
        'assigned'  => (clone $base)->where('status', 'assigned')->count(),
    ];

    $sessions = (clone $base)
        ->where('status', $tab)
        ->when($search !== '', fn ($q) => $q->whereHas('learner', function ($learner) use ($search) {
            $learner->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%");
        }))
        ->with([
            'learner:id,first_name,middle_name,last_name',
            'assessment:id,title,assessment_type_id,section_id,quarter_id',
            'assessment.assessmentType:id,name,code',
            'assessment.section:id,section_name',
            'assessment.quarter:id,quarter',
            'sessionQuestions:id,exercise_session_id,selected_option_id,response_text',
        ])
        ->when(
            $tab === 'submitted',
            fn ($q) => $q->orderByDesc('submitted_at')->orderByDesc('id'),
            fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'),
        )
        ->paginate(25)
        ->withQueryString()
        ->through(fn (ExerciseSession $session) => $this->mapSubmissionRow($session));

    return Inertia::render('Exercises/Submissions', [
        'tab' => $tab,
        'search' => $search,
        'counts' => $counts,
        'sessions' => $sessions,
    ]);
}

/**
 * @return array<string, mixed>
 */
private function mapSubmissionRow(ExerciseSession $session): array
{
    return [
        'id' => $session->id,
        'assessment_id' => $session->assessment?->id,
        'assessment_title' => $session->assessment?->title,
        'type' => $session->assessment?->assessmentType?->name,
        'section' => $session->assessment?->section?->section_name,
        'quarter' => $session->assessment?->quarter?->quarter,
        'kind' => $session->kind,
        'learner' => $session->learner ? [
            'id' => $session->learner->id,
            'name' => trim(collect([
                $session->learner->last_name,
                $session->learner->first_name,
            ])->filter()->implode(', ')),
        ] : null,
        'submitted_at' => $session->submitted_at?->toIso8601String(),
        'created_at' => $session->created_at?->toIso8601String(),
        'answered_count' => $session->answered_count,
        'total_questions' => $session->sessionQuestions->count(),
    ];
}
```

Notes:
- Row shape deliberately mirrors the dashboard `remediation.pending` payload so the two
  views stay consistent.
- `answered_count` / `total_questions` come from the appended accessor + eager-loaded
  `sessionQuestions`; keep the eager load or the page lazy-loads per row.
- `clone $base` is required so the count queries and the paginated query do not mutate the
  shared builder.
- `withQueryString()` keeps `tab` + `search` in pagination links.

## Frontend

### `resources/js/Pages/Exercises/Submissions.vue` (new)

`MainAuthLayout` page. Mirror the visual language of `Exercises/Index.vue` (slate/indigo
header card, rounded-3xl tables) and the search/pagination conventions from
`ErrorLogs/Index.vue` + `StudentMainContent.vue`.

Script:
- Props: `tab` (String, default `'submitted'`), `search` (String, default `''`),
  `counts` (Object, default `{ submitted: 0, assigned: 0 }`), `sessions` (Object paginator,
  default `{ data: [], links: [], total: 0 }`).
- `const searchInput = ref(props.search);` seeded from the prop.
- `applySearch` / tab switching call
  `router.get(route('exercises.submissions.index'), { tab, search }, { preserveState: true, replace: true, preserveScroll: true })`.
- Debounce the search input ~300 ms (copy the timer pattern from
  `StudentMainContent.vue`); commit on `@keyup.enter` too.
- Tab click must carry the current `searchInput`; search must carry the current `tab`.
- `rows` = `props.sessions.data ?? []`.
- Helpers copied verbatim from `Exercises/Index.vue`: `kindLabel`, `kindClasses`; and a
  `formatDateTime` like `Dashboard.vue`.

Template:
- Header card: title "Exercise Submissions", subtitle noting it covers the current school
  year, plus a search `<input v-model="searchInput">`.
- Two tab buttons with count chips, styled like `Tracker/Index.vue` tabs:
  - `Awaiting marking` → `counts.submitted`, active when `tab === 'submitted'`.
  - `Did not answer yet` → `counts.assigned`, active when `tab === 'assigned'`.
- Table columns: Learner, Kind, Assessment (title fallback to `type`), Section, Quarter,
  Answered (`answered_count / total_questions`), and a date column that shows
  `submitted_at` on the submitted tab and `created_at` (labelled "Given") on the assigned
  tab. Actions: `Open` link to `route('exercise-sessions.show', session.id)`.
- Empty states per tab:
  - submitted: "Nothing is waiting to be marked."
  - assigned: "Every assigned exercise has been answered."
- Pagination markup mirroring `ErrorLogs/Index.vue:373-390` (iterate `sessions.links`,
  `Link` with `preserve-scroll`, disable non-url links); show only when `links.length > 3`.

### `resources/js/Pages/Dashboard.vue` (link only)

In the "Awaiting marking" card header (around lines 167-176), add a `View all` `Link`
next to the count chip pointing at `route('exercises.submissions.index')`. Keep the
existing `#awaiting-marking` anchor behaviour of the stat card unchanged. The dashboard
list remains capped at 8 and no backend dashboard props change.

## Tests — `tests/Feature/Exercises/ExerciseSubmissionsTest.php` (new)

Use the repo MySQL convention (`DB_CONNECTION=mysql DB_DATABASE=class_record_test`); run
`npm run build` before page-render assertions. Build fixtures like
`tests/Feature/Exercises/ExercisePageTest.php` (school year, grade level, section,
quarter, type, admin, learners + enrollments, topic, questionnaire, assessment).

**Do not redeclare existing global Pest helpers** (`makeRemediationAssessment`,
`addRemediationQuestion(s)`, `addRemediationMcq`, `failingRow`, `sr*`, `ep*`). Prefix new
helpers with `esub` (e.g. `esubAssessment`, `esubAddText`, `esubSession`).

Coverage:
- Admin can open `exercises.submissions.index` → `assertOk` + `component('Exercises/Submissions')`
  + `has('sessions.data')` + default `tab === 'submitted'` + `counts` present.
- Non-admin (student) forbidden.
- Create one `submitted` and one `assigned` session; assert `?tab=assigned` returns only the
  assigned session and `?tab=submitted` only the submitted one (assert ids/kinds in
  `sessions.data`).
- `?search=` filters by learner first/last name and excludes non-matching learners.
- Counts reflect both statuses for the current school year.
- Regression: run the full `tests/Feature/Exercises/` directory and `tests/Feature/DashboardTest.php`
  unchanged.

## Verification order

1. `php artisan migrate --force` (no new migrations expected).
2. `php vendor/bin/pint` on changed/new PHP.
3. `npm run build` (the new page must be in the Vite manifest before page assertions).
4. `DB_CONNECTION=mysql DB_DATABASE=class_record_test php artisan test tests/Feature/Exercises/ExerciseSubmissionsTest.php`
   plus the rest of `tests/Feature/Exercises/` and `tests/Feature/DashboardTest.php`.
5. Manual smoke as admin: dashboard → "Awaiting marking" → "View all" opens the page on the
   Awaiting-marking tab; type a learner name and confirm filtering; switch to "Did not
   answer yet" and confirm the search term is kept; paginate and confirm `tab`/`search`
   survive; confirm `Open` lands on `exercise-sessions.show`.

## Risks / notes

- **Duplicate row shape:** `mapSubmissionRow` intentionally duplicates the dashboard's
  `pendingSessions` mapping. Accept the duplication for now (a shared mapper could be
  extracted later); keep the two in sync if fields change.
- **Route ordering:** `/exercises/submissions` is a static GET and there is no competing
  `GET /exercises/{param}`, so no capture conflict with `/exercises/{assessment}/sessions`
  (POST/DELETE) or `/exercises/students/{learner}`.
- **Assigned sessions are effectively blank:** the only write path that records answers is
  the student submit action, which also flips status to `submitted`. So tab 2 rows will
  normally show `0 / total`. The numeric column is kept for parity; label the date column
  "Given" on that tab.
- **Payload size:** 25 rows/page; each row eager-loads `sessionQuestions`. For very large
  schools this is acceptable at 25/page but avoid raising the page size without also moving
  to SQL aggregates.
- **Search is name-only** by decision; do not add assessment/section matching without
  confirming.
