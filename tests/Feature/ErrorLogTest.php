<?php

use App\Models\ErrorLog;
use App\Models\User;
use App\Services\ErrorLogger;
use App\Services\LogFileReader;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function errorLogAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();

    return $admin;
}

function errorLogRow(array $overrides = []): ErrorLog
{
    return ErrorLog::create(array_merge([
        'fingerprint' => sha1('sample'),
        'level' => 'error',
        'status_code' => 500,
        'exception_class' => RuntimeException::class,
        'message' => 'Sample failure',
        'stack_trace' => '#0 {main}',
    ], $overrides));
}

test('admins can view the error logs page', function () {
    $this->actingAs(errorLogAdmin())
        ->get(route('error-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ErrorLogs/Index')
            ->has('filters')
            ->has('summary')
            ->has('logs')
            ->has('logFiles')
        );
});

test('non-admins cannot view the error logs page', function () {
    $student = User::factory()->create();
    $student->forceFill(['role' => 'student', 'status' => 'active'])->save();

    $this->actingAs($student)->get(route('error-logs.index'))->assertForbidden();
});

test('the error logger captures a 5xx exception with request context', function () {
    config()->set('error_log.capture_in_tests', true);

    $admin = errorLogAdmin();
    $request = Request::create('http://localhost/admin/students', 'GET');
    $request->setUserResolver(fn () => $admin);

    app(ErrorLogger::class)->capture(new RuntimeException('Something exploded'), $request);

    $log = ErrorLog::first();

    expect($log)->not->toBeNull()
        ->and($log->exception_class)->toBe(RuntimeException::class)
        ->and($log->status_code)->toBe(500)
        ->and($log->level)->toBe('error')
        ->and($log->message)->toBe('Something exploded')
        ->and($log->method)->toBe('GET')
        ->and($log->url)->toBe('http://localhost/admin/students')
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->user_role)->toBe('admin')
        ->and($log->stack_trace)->not->toBeEmpty();
});

test('the error logger does not capture non-5xx http exceptions', function () {
    config()->set('error_log.capture_in_tests', true);

    $request = Request::create('http://localhost/missing', 'GET');

    app(ErrorLogger::class)->capture(new NotFoundHttpException('Not Found'), $request);

    expect(ErrorLog::count())->toBe(0);
});

test('the log file parser splits entries, parses fields, and applies filters', function () {
    $sample = <<<'LOG'
[2026-04-05 13:41:26] local.ERROR: First failure {"exception":"[object]"}
#0 /app/First.php(10): boom()
#1 {main}

[2026-04-05 13:41:30] local.WARNING: Something odd
#0 /app/Second.php(20): warn()

[2026-04-05 13:42:01] local.ERROR: Second failure
#0 /app/Third.php(30): boom()
LOG;

    $reader = new LogFileReader;

    $entries = $reader->parse($sample);

    expect($entries)->toHaveCount(3)
        ->and($entries[0]['timestamp'])->toBe('2026-04-05 13:42:01')
        ->and($entries[0]['level'])->toBe('ERROR')
        ->and($entries[0]['message'])->toBe('Second failure')
        ->and($entries[0]['trace'])->toContain('Third.php');

    $errors = $reader->parse($sample, 'ERROR');

    expect($errors)->toHaveCount(2);

    $searched = $reader->parse($sample, null, 'warn()');

    expect($searched)->toHaveCount(1)
        ->and($searched[0]['message'])->toBe('Something odd');
});

test('the log file reader refuses paths outside the logs directory', function () {
    $reader = new LogFileReader;

    expect($reader->read('../../.env'))->toBe([])
        ->and($reader->read('laravel.log.bak'))->toBe([]);
});

test('admins can delete a single error log', function () {
    $log = errorLogRow();

    $this->actingAs(errorLogAdmin())
        ->delete(route('error-logs.destroy', $log->id))
        ->assertRedirect();

    expect(ErrorLog::count())->toBe(0);
});

test('admins can clear all error logs', function () {
    errorLogRow();
    errorLogRow();

    $this->actingAs(errorLogAdmin())
        ->delete(route('error-logs.destroy-all'))
        ->assertRedirect();

    expect(ErrorLog::count())->toBe(0);
});
