<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ErrorLogger
{
    /**
     * Capture an unhandled exception that would render as a 5xx response.
     *
     * Never throws: any failure falls back to the file logger so logging can
     * never break the original request.
     */
    public function capture(Throwable $e, ?Request $request = null): void
    {
        if (! config('error_log.enabled', true)) {
            return;
        }

        if (app()->runningUnitTests() && ! config('error_log.capture_in_tests', false)) {
            return;
        }

        if (! $this->isWorthCapturing($e)) {
            return;
        }

        try {
            if (! Schema::hasTable('error_logs')) {
                return;
            }

            $request ??= request();

            ErrorLog::create($this->attributes($e, $request));
        } catch (Throwable $inner) {
            try {
                Log::error('error_log capture failed', [
                    'exception' => $inner->getMessage(),
                    'original' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Never let logging break the request.
            }
        }
    }

    /**
     * Only store exceptions that would render as 5xx responses.
     */
    protected function isWorthCapturing(Throwable $e): bool
    {
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException) {
            return false;
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return false;
        }

        return true;
    }

    protected function statusCode(Throwable $e): int
    {
        return $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    }

    protected function level(Throwable $e): string
    {
        return $e instanceof \Error ? 'critical' : 'error';
    }

    public function fingerprint(Throwable $e): string
    {
        return sha1(implode('|', [
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            (string) $e->getLine(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(Throwable $e, ?Request $request): array
    {
        $user = $request?->user();

        return [
            'fingerprint' => $this->fingerprint($e),
            'level' => $this->level($e),
            'status_code' => $this->statusCode($e),
            'exception_class' => $e::class,
            'message' => (string) $e->getMessage(),
            'code' => (string) $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $request?->method(),
            'url' => $request?->fullUrl(),
            'route_name' => $request?->route()?->getName(),
            'user_id' => $user?->getKey(),
            'user_role' => $user?->role ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => [
                'app_env' => app()->environment(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'stack_trace' => $e->getTraceAsString(),
        ];
    }
}
