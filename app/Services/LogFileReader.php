<?php

namespace App\Services;

class LogFileReader
{
    /**
     * List the log files without exposing full paths.
     *
     * @return array<int, array{name: string, size: int, modified_at: string}>
     */
    public function files(): array
    {
        $paths = glob(storage_path('logs/*.log')) ?: [];

        $files = [];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) (filesize($path) ?: 0),
                'modified_at' => date(DATE_ATOM, (int) (filemtime($path) ?: time())),
            ];
        }

        usort($files, fn (array $a, array $b): int => strcmp($b['modified_at'], $a['modified_at']));

        return $files;
    }

    /**
     * Read the tail of a log file, newest first, applying optional filters.
     *
     * @return array<int, array{timestamp: string, level: string, message: string, trace: string}>
     */
    public function read(string $file, ?int $maxBytes = null, ?string $level = null, ?string $search = null): array
    {
        $path = $this->resolve($file);

        if ($path === null) {
            return [];
        }

        $maxBytes ??= (int) config('error_log.file_view.max_bytes', 2 * 1024 * 1024);
        $maxEntries = (int) config('error_log.file_view.max_entries', 200);

        $tail = $this->tail($path, $maxBytes);

        if ($tail === '') {
            return [];
        }

        return $this->parse($tail, $level, $search);
    }

    /**
     * Parse raw log contents into entries (newest first), applying filters.
     *
     * @return array<int, array{timestamp: string, level: string, message: string, trace: string}>
     */
    public function parse(string $contents, ?string $level = null, ?string $search = null): array
    {
        $maxEntries = (int) config('error_log.file_view.max_entries', 200);

        $chunks = preg_split('/^(?=\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/m', $contents) ?: [];

        $entries = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk, "\r\n");

            if ($chunk === '') {
                continue;
            }

            $entry = $this->parseEntry($chunk);

            if ($entry === null) {
                continue;
            }

            if ($level !== null && $level !== '' && strcasecmp($entry['level'], $level) !== 0) {
                continue;
            }

            if ($search !== null && $search !== '') {
                $haystack = $entry['message']."\n".$entry['trace'];

                if (stripos($haystack, $search) === false) {
                    continue;
                }
            }

            $entries[] = $entry;
        }

        $entries = array_reverse($entries);

        return array_slice($entries, 0, $maxEntries);
    }

    /**
     * Resolve and validate a log filename inside storage/logs (no traversal).
     */
    protected function resolve(string $file): ?string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+\.log$/', $file)) {
            return null;
        }

        $base = realpath(storage_path('logs'));

        if ($base === false) {
            return null;
        }

        $path = realpath(storage_path('logs/'.$file));

        if ($path === false || ! is_file($path)) {
            return null;
        }

        $base = rtrim(str_replace('\\', '/', $base), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        if (! str_starts_with($normalized, $base)) {
            return null;
        }

        return $path;
    }

    /**
     * Read only the last $maxBytes of the file, skipping a partial first line.
     */
    protected function tail(string $path, int $maxBytes): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            $size = filesize($path) ?: 0;
            $start = max(0, $size - max(1, $maxBytes));

            if ($start > 0) {
                fseek($handle, $start);
                fgets($handle);
            } else {
                fseek($handle, 0);
            }

            $contents = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        return $contents === false ? '' : $contents;
    }

    /**
     * @return array{timestamp: string, level: string, message: string, trace: string}|null
     */
    protected function parseEntry(string $chunk): ?array
    {
        $lines = explode("\n", $chunk);
        $first = $lines[0] ?? '';

        if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ([^\.]+)\.([A-Za-z]+): (.*)$/', $first, $matches)) {
            return null;
        }

        $trace = trim(implode("\n", array_slice($lines, 1)), "\r\n");

        return [
            'timestamp' => $matches[1],
            'level' => strtoupper($matches[3]),
            'message' => $matches[4],
            'trace' => $trace,
        ];
    }
}
