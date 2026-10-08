<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error log capture
    |--------------------------------------------------------------------------
    |
    | When enabled, unhandled exceptions that would render as HTTP 5xx are
    | stored in the "error_logs" table for review from the admin Error Logs
    | page. Capture never throws: any failure falls back to the file logger.
    |
    */

    'enabled' => env('ERROR_LOG_ENABLED', true),

    /*
    | Capture is skipped while running the test suite unless this is enabled,
    | so the suite stays hermetic. Tests that need to exercise capture may
    | call the service directly with this flag turned on.
    */
    'capture_in_tests' => env('ERROR_LOG_CAPTURE_IN_TESTS', false),

    /*
    |--------------------------------------------------------------------------
    | Log file viewer
    |--------------------------------------------------------------------------
    |
    | Bounds for the read-only "Log files" tab so a large laravel.log cannot
    | exhaust memory or bloat the Inertia payload.
    |
    */

    'file_view' => [
        'max_bytes' => (int) env('ERROR_LOG_MAX_BYTES', 2 * 1024 * 1024),
        'max_entries' => (int) env('ERROR_LOG_MAX_ENTRIES', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Number of captured database rows shown per page on the Database tab.
    |
    */

    'per_page' => (int) env('ERROR_LOG_PER_PAGE', 25),

];
