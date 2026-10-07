<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use App\Services\LogFileReader;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ErrorLogController extends Controller
{
    public function index(Request $request, LogFileReader $reader)
    {
        $source = $request->string('source', 'db')->toString();

        if (! in_array($source, ['db', 'files'], true)) {
            $source = 'db';
        }

        $filters = [
            'search' => $request->string('search')->toString(),
            'level' => $request->string('level')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
            'file' => $request->string('file')->toString(),
            'source' => $source,
        ];

        $logFiles = $reader->files();

        $selectedFile = null;
        $fileEntries = [];

        if ($source === 'files') {
            $selectedFile = $filters['file'] !== '' ? $filters['file'] : ($logFiles[0]['name'] ?? null);

            if ($selectedFile !== null && collect($logFiles)->pluck('name')->contains($selectedFile)) {
                $fileEntries = $reader->read(
                    $selectedFile,
                    null,
                    $filters['level'] !== '' ? $filters['level'] : null,
                    $filters['search'] !== '' ? $filters['search'] : null,
                );
            } else {
                $selectedFile = null;
            }
        }

        $logs = $this->dbQuery($filters)
            ->with('user:id,name,role')
            ->latest()
            ->paginate((int) config('error_log.per_page', 25))
            ->withQueryString()
            ->through(fn (ErrorLog $log) => $this->mapLog($log));

        return Inertia::render('ErrorLogs/Index', [
            'filters' => $filters,
            'summary' => $this->summary(),
            'logs' => $logs,
            'logFiles' => $logFiles,
            'selectedFile' => $selectedFile,
            'fileEntries' => $fileEntries,
        ]);
    }

    public function destroy(ErrorLog $errorLog)
    {
        $errorLog->delete();

        return back()->with('success', 'Error log entry deleted.');
    }

    public function destroyAll()
    {
        ErrorLog::query()->delete();

        return back()->with('success', 'All error logs cleared.');
    }

    protected function dbQuery(array $filters): Builder
    {
        return ErrorLog::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('message', 'like', "%{$search}%")
                        ->orWhere('exception_class', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%");
                });
            })
            ->when($filters['level'] !== '', fn (Builder $query) => $query->where('level', $filters['level']))
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']));
    }

    /**
     * @return array{total: int, today: int, week: int}
     */
    protected function summary(): array
    {
        return [
            'total' => ErrorLog::count(),
            'today' => ErrorLog::whereDate('created_at', today())->count(),
            'week' => ErrorLog::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapLog(ErrorLog $log): array
    {
        return [
            'id' => $log->id,
            'level' => $log->level,
            'status_code' => $log->status_code,
            'exception_class' => $log->exception_class,
            'message' => $log->message,
            'code' => $log->code,
            'file' => $log->file,
            'line' => $log->line,
            'method' => $log->method,
            'url' => $log->url,
            'route_name' => $log->route_name,
            'user' => [
                'id' => $log->user?->id,
                'name' => $log->user?->name,
                'role' => $log->user_role ?? $log->user?->role,
            ],
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->toISOString(),
            'stack_trace' => $log->stack_trace,
            'context' => $log->context,
        ];
    }
}
