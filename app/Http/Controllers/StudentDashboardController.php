<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\QuarterlyAssessment;
use App\Models\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $schoolYear = SchoolYear::current();

        $learner = $user?->learner;
        $section = null;
        $assessments = collect();

        if ($learner) {
            $enrollmentQuery = $learner->enrollments()->with(['section.gradeLevel']);

            if ($schoolYear) {
                $enrollmentQuery->where('school_year_id', $schoolYear->id);
            }

            $enrollment = $enrollmentQuery->latest('updated_at')->first();
            $section = $enrollment?->section;

            if ($section) {
                $assessments = QuarterlyAssessment::with([
                    'quarter',
                    'section.gradeLevel',
                    'schoolYear',
                    'user',
                ])
                    ->where('section_id', $section->id)
                    ->when($schoolYear, fn ($query) => $query->where('school_year_id', $schoolYear->id))
                    ->orderBy('quarter_id')
                    ->get()
                    ->map(fn ($assessment) => $this->transformAssessment($assessment, $learner));
            }
        }

        return Inertia::render('Students/Dashboard', [
            'student' => $learner ? [
                'first_name' => $learner->first_name,
                'middle_name' => $learner->middle_name,
                'last_name' => $learner->last_name,
                'email' => $learner->email,
            ] : null,
            'section' => $section ? [
                'id' => $section->id,
                'section_name' => $section->section_name,
                'grade_level' => $section->gradeLevel?->grade_level,
            ] : null,
            'schoolYear' => $schoolYear ? [
                'id' => $schoolYear->id,
                'year_start' => $schoolYear->year_start,
                'year_end' => $schoolYear->year_end,
            ] : null,
            'quarterlyAssessments' => $assessments->values()->all(),
        ]);
    }

    protected function transformAssessment(QuarterlyAssessment $assessment, Learner $learner): array
    {
        $studentRow = $this->extractStudentRow($assessment, $learner);

        return [
            'id' => $assessment->id,
            'quarter' => [
                'id' => $assessment->quarter?->id,
                'name' => $assessment->quarter?->name,
                'index' => $assessment->quarter?->quarter,
            ],
            'section' => [
                'id' => $assessment->section?->id,
                'section_name' => $assessment->section?->section_name,
                'grade_level' => $assessment->section?->gradeLevel?->grade_level,
            ],
            'schoolYear' => [
                'id' => $assessment->schoolYear?->id,
                'year_start' => $assessment->schoolYear?->year_start,
                'year_end' => $assessment->schoolYear?->year_end,
            ],
            'uploadedAt' => $assessment->created_at?->toISOString(),
            'uploadedBy' => $assessment->user?->name,
            'studentRow' => $studentRow,
            'hasPayload' => ! empty($assessment->assessment),
        ];
    }

    protected function extractStudentRow(QuarterlyAssessment $assessment, Learner $learner): array
    {
        $payload = $assessment->assessment ?? [];

        if (empty($payload) || ! is_array($payload)) {
            return [
                'headers' => [],
                'values' => [],
                'found' => false,
            ];
        }

        $headers = array_values($payload['headers'] ?? []);
        $rows = $payload['rows'] ?? [];

        if (! is_array($rows)) {
            $rows = [];
        }

        $rows = array_map(fn ($row) => is_array($row) ? array_values($row) : [], $rows);

        $match = $this->matchLearnerRow($rows, $learner);
        $subHeaders = $match ? $this->collectSubHeaders($rows, $match['index']) : [];
        $firstRow = $rows[0] ?? null;

        if ($firstRow && (! $subHeaders || $firstRow !== $subHeaders[0])) {
            array_unshift($subHeaders, $firstRow);
        }
        $subheaderValues = $this->resolveSubheaderValues($subHeaders);

        return [
            'headers' => $headers,
            'values' => $match['row'] ?? [],
            'subheaders' => $subHeaders,
            'subheader_values' => $subheaderValues,
            'found' => isset($match['row']),
        ];
    }

    protected function matchLearnerRow(array $rows, Learner $learner): ?array
    {
        $terms = $this->buildSearchTerms($learner);

        foreach ($rows as $index => $row) {
            foreach ($row as $column) {
                $value = Str::lower(trim((string) $column));

                if ($value === '') {
                    continue;
                }

                foreach ($terms as $term) {
                    if ($term && str_contains($value, $term)) {
                        return [
                            'row' => $row,
                            'index' => $index,
                        ];
                    }
                }
            }
        }

        return null;
    }

    protected function collectSubHeaders(array $rows, int $currentIndex): array
    {
        $subHeaders = [];

        for ($i = $currentIndex - 1; $i >= 0; $i--) {
            $row = $rows[$i];

            if ($this->isSubHeaderRow($row)) {
                array_unshift($subHeaders, $row);
                continue;
            }

            $hasNames = trim((string) ($row[0] ?? '')) !== '' || trim((string) ($row[1] ?? '')) !== '';

            if ($hasNames) {
                break;
            }
        }

        return $subHeaders;
    }

    protected function isSubHeaderRow(array $row): bool
    {
        $first = trim((string) ($row[0] ?? ''));
        $second = trim((string) ($row[1] ?? ''));

        if ($first !== '' || $second !== '') {
            return false;
        }

        return collect($row)->contains(fn ($value) => trim((string) $value) !== '');
    }

    protected function resolveSubheaderValues(array $subHeaders): array
    {
        if (empty($subHeaders)) {
            return [];
        }

        $last = end($subHeaders);

        return array_values(is_array($last) ? $last : []);
    }

    protected function buildSearchTerms(Learner $learner): array
    {
        return array_values(array_filter([
            Str::lower($learner->email ?? ''),
            Str::lower(trim("{$learner->first_name} {$learner->last_name}")),
            Str::lower(trim("{$learner->last_name}, {$learner->first_name}")),
            Str::lower($learner->last_name ?? ''),
            Str::lower($learner->first_name ?? ''),
        ]));
    }
}
