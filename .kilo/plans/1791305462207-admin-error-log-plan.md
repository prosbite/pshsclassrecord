# Admin Error Log (DB capture + file viewer)

Give admins a single page to debug production 500s. Unhandled exceptions are captured to a
database table, and an admin-only page lists them with search/filters plus a second tab that
reads the existing `storage/logs/*.log` files. Tooling only — diagnosing the current student
500 is a follow-up once its trace is visible.

## Locked Decisions

1. **Two sources, one page.** A DB tab (captures new errors, structured/queryable) + a Files tab
   (reads existing `storage/logs/*.log`, so the already-logged student 500 is visible immediately).
2. **Capture unhandled exceptions only.** Store exceptions that render as HTTP 5xx. Skip 4xx
   (`ValidationException`, `AuthenticationException`, `AuthorizationException`, and any
   `HttpExceptionInterface` with status < 500) to keep the log actionable.
3. **Admins only**, via the existing `EnsureUserIsAdmin` middleware (route group already exists).
4. **Read-only file view.** The Files tab never deletes/modifies log files. Only DB rows can be deleted.
5. **Tooling only.** No attempt to fix the student 500 in this plan.
6. **Never let logging break a request.** Capture is fully wrapped in `try/catch`; on failure it
   falls back to `Log::error()` and returns. Missing `error_logs` table or a down DB must not recurse
   or add a second exception.

## Confirmed context

- Laravel 13 / PHP 8.3, Inertia v2, Pest 4, MySQL. Log channel is `single` → `storage/logs/laravel.log`
  (see `config/logging.php`). Log line format confirmed:
  `[YYYY-MM-DD HH:MM:SS] env.LEVEL: message {"exception":"..."}` followed by raw stack-trace lines
  until the next matching timestamp line.
- Admin routes live in the `Route::prefix('admin')` group in `routes/web.php` guarded by
  `['auth','verified', EnsureUserIsAdmin::class]`.
- Admin nav is `resources/js/Layouts/MainAuthLayout.vue` (`navItems` array + heroicons).
- `bootstrap/app.php` already has an empty `->withExceptions(...)` hook to fill.
- Existing read-only listing pattern to mirror: `TrackerController` + `resources/js/Pages/Tracker/Index.vue`.
- The uncommitted enhancement-exercise work is in the tree; do not disturb it.

## Schema

New migration `database/migrations/2026_10_07_000001_create_error_logs_table.php`:

| column | type | notes |
|---|---|---|
| id | id | |
| fingerprint | string(40) | sha1(class+message+file+line), indexed (future grouping) |
| level | string | `error` / `critical` |
| status_code | unsignedSmallInteger | resolved HTTP status (500 default) |
| exception_class | string | FQCN |
| message | text | |
| code | string nullable | `$e->getCode()` cast to string |
| file | string nullable | |
| line | unsignedInteger nullable | |
| method | string nullable | request method |
| url | text nullable | full request URL |
| route_name | string nullable | current route name |
| user_id | foreignId nullable, `nullOnDelete` | requesting user |
| user_role | string nullable | denormalized for filtering |
| ip_address | string(45) nullable | |
| user_agent | text nullable | |
| context | json nullable | `app_env`, `php_version`, `laravel_version` |
| stack_trace | longText | `$e->getTraceAsString()` (or `getTrace()` rendered) |
| timestamps | | index on `created_at` |

## Models / Services

### `app/Models/ErrorLog.php` (new)
- `protected $fillable` = all columns above except `id`/timestamps.
- casts: `context` => `array`, `created_at`/`updated_at` => `datetime`.
- `user()` belongsTo `User` (nullable).

### `app/Services/ErrorLogger.php` (new)
- `capture(Throwable $e, ?Request $request = null): void`
  - `$request ??= request();` (nullable — CLI context has no request).
  - Skip when `app()->runningUnitTests()` unless `config('error_log.capture_in_tests')` is true
    (keeps the suite hermetic; tests that need capture call the service directly).
  - Filter as per decision 2. Resolve `status_code` = `HttpExceptionInterface::getStatusCode()` else 500.
  - Build the row (see schema). **Never** store the request body / headers / password fields.
  - Persist inside `try { ... } catch (Throwable $inner) { Log::error('error_log capture failed', [...]); }`.
  - Guard with `Schema::hasTable('error_logs')` so a not-yet-migrated deploy cannot throw.
- Small helpers: `fingerprint(Throwable): string`, `isWorthCapturing(Throwable): bool`,
  `statusCode(Throwable): int`.

### `app/Services/LogFileReader.php` (new)
- `files(): array` — glob `storage_path('logs/*.log')`, return `name`, `size`, `modified_at`.
- `read(string $file, int $maxBytes, ?string $level, ?string $search): array`
  - Validate `$file` against `^[A-Za-z0-9._-]+\.log$`; resolve real path and assert it is inside
    `storage_path('logs')` (no traversal).
  - Read only the **last** `maxBytes` (default 2 MB) of the file, then split into entries with
    `preg_split('/^\[(?=\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/m', $tail)`.
  - Per entry parse `^\[(ts)\] \S+\.(LEVEL): (message)` (first line) and keep the remainder as
    `trace`. Return `timestamp`, `level`, `message`, `trace`.
  - Apply `level` and `search` (case-insensitive substring across message+trace) filters in PHP,
    newest-first, cap to a sane count (e.g. 200 entries) to bound the Inertia payload.

### `bootstrap/app.php`
- In `withExceptions`, register a report callback:
  ```php
  $exceptions->report(function (Throwable $e): void {
      app(\App\Services\ErrorLogger::class)->capture($e);
  });
  ```
  (Verify exact method name against Laravel 13 `Illuminate\Foundation\Configuration\Exceptions`;
  `report(callable)` is the intended hook.)

### `config/error_log.php` (new)
- `enabled` (`ERROR_LOG_ENABLED`, default true), `capture_in_tests` (default false),
  `file_view.max_bytes` (2 MB), `file_view.max_entries` (200), `per_page` (25).

## Controller / Routes

### `app/Http/Controllers/ErrorLogController.php` (new)
- `index(Request)` → `Inertia::render('ErrorLogs/Index', [...])` with:
  - `filters` = `search`, `level`, `date_from`, `date_to`, `file`, `source` (`db`|`files`).
  - DB tab: paginated `logs` (latest first; `search` over message/exception_class/url, `level`,
    date range), each row mapped to id, level, status_code, exception_class, message, file, line,
    method, url, route_name, user (name/role), ip_address, created_at, stack_trace, context.
  - `summary` = total, today, last 7 days.
  - Files tab: `logFiles` (from `LogFileReader::files()`) and, when a file is selected,
    `fileEntries` + the resolved `selectedFile`.
- `destroy(ErrorLog $errorLog)` → delete, redirect back with `success`.
- `destroyAll()` → delete all rows, redirect back with `success` (guard with a confirm in the UI).

### `routes/web.php` (inside the admin group)
```php
Route::get('/error-logs', [ErrorLogController::class, 'index'])->name('error-logs.index');
Route::delete('/error-logs', [ErrorLogController::class, 'destroyAll'])->name('error-logs.destroy-all');
Route::delete('/error-logs/{errorLog}', [ErrorLogController::class, 'destroy'])->name('error-logs.destroy');
```
Static routes declared before the parameterized one.

### `resources/js/Layouts/MainAuthLayout.vue`
- Add nav item `{ label: 'Error Logs', href: errorLogsPath, active: isErrorLogsActive, icon: ExclamationTriangleIcon }`
  (import the icon; mirror the existing `normalizePath`/`startsWith` pattern). A count badge is
  optional and out of scope unless requested (it would add a per-request query).

### `resources/js/Pages/ErrorLogs/Index.vue` (new)
- `MainAuthLayout`; source toggle **Database** / **Log files** (drives `source` via
  `router.get(route('error-logs.index'), params, { preserveState: true, replace: true })`).
- DB view: filter bar (search, level select, date-from/to), summary tiles, table
  (created_at, level, status, exception_class, message, url, user), expandable row showing the full
  stack trace + context, per-row Delete, and a **Clear all** button with `window.confirm`.
- Files view: file `<select>` from `logFiles`, level select + search, list of entries with
  expandable trace, note "read-only".
- Follow existing styling conventions (rounded-3xl white cards, slate/indigo accents).

## Tests — `tests/Feature/ErrorLogTest.php` (new)

- Admin can open `error-logs.index` → `assertOk` + `assertInertia(component('ErrorLogs/Index'))`
  with `logs`/`summary`/`logFiles` present.
- Non-admin (student) is forbidden (`assertForbidden`), matching admin-route tests.
- `ErrorLogger::capture()` stores a row with expected class/status/url/message/stack fields
  (call the service directly with a thrown exception and a bound request); a 404 `NotFoundHttpException`
  is **not** stored.
- `LogFileReader::read()` parses a multi-entry sample string into the right timestamp/level/message/trace
  and applies level/search filters (feed the parser directly to avoid writing to `storage/logs`).
- `destroy` removes one row; `destroyAll` removes all.

Use the repo's MySQL test convention (`DB_CONNECTION=mysql DB_DATABASE=class_record_test`).
Do not redeclare existing Pest helper names (see `tests/Feature/Exercises/*`).

## Verification

- `php artisan migrate --force`.
- `php vendor/bin/pint` on changed/new PHP.
- `npm run build` (new Vue page must be in the Vite manifest before tests/deploy).
- `DB_CONNECTION=mysql DB_DATABASE=class_record_test php artisan test tests/Feature/ErrorLogTest.php`.
- Manual smoke: as admin open `/admin/error-logs`; Files tab shows the existing entries in
  `storage/logs/laravel.log`; trigger an unhandled 5xx locally and confirm it appears in the DB tab
  with a full trace; delete one row and Clear all.
- Live: run `php artisan migrate --force`, `npm run build`, `php artisan optimize:clear`, ensure
  `storage/logs` is readable and writable, then reproduce the student 500 (admin impersonation on the
  Students page) and read the trace from the page.

## Risks / notes

- **Most likely root cause of the student 500 (hypothesis, not a fix here):** the newest migrations
  may not have been run on live, so `$learner->exerciseSessions()` hits a missing `exercise_sessions`
  table (`QueryException: Base table or view not found`). The Files tab should reveal this immediately.
- **Logging must never throw.** Keep the whole capture path in try/catch with a file fallback and a
  `Schema::hasTable` guard; never serialize request input.
- **Sensitive data:** stack traces/URLs may contain identifiers. Page is admin-only; do not store
  request bodies, headers, or credentials.
- **File reading bounds:** cap bytes/entries so a large `laravel.log` cannot exhaust memory or bloat
  the Inertia payload; restrict to `storage/logs` with an allow-list filename regex.
- **Read access on live:** if `storage/logs` is not readable by PHP, the Files tab shows a friendly
  empty/error state; DB capture still works.
- **No retention policy** in scope: growth is managed by Clear all. A scheduled prune command is a
  possible follow-up.

## Out of scope

- Fixing the student 500 (follow-up once the trace is in hand).
- Grouping by fingerprint, JSON export, alerting/notifications, `Log::error()` mirroring.
- Deleting/rotating actual log files from the UI.
