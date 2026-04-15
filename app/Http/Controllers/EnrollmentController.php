<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EnrollmentController extends Controller
{
    public function bulkRegister(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        [$header, $rows] = $this->loadSpreadsheetRows($request);

        if (empty($rows)) {
            return response()->json(['error' => 'The uploaded file is empty'], 422);
        }

        $header = array_map(fn ($value) => Str::snake(trim((string) $value)), $header);

        $requiredColumns = [
            'first_name',
            'last_name',
            'middle_name',
            'gender',
            'grade_level',
            'section',
        ];

        $missingColumns = array_diff($requiredColumns, $header);
        if (!empty($missingColumns)) {
            return response()->json([
                'error' => 'Missing columns: ' . implode(', ', $missingColumns),
            ], 422);
        }

        $results = ['created' => [], 'updated' => [], 'failed' => []];
        $totalProcessed = 0;

        $schoolYear = SchoolYear::current() ?? SchoolYear::create([
            'year_start' => now()->year,
            'year_end' => now()->year + 1,
            'status' => 'active',
        ]);

        $reservedUsernames = User::whereNotNull('username')
            ->pluck('username')
            ->filter()
            ->map(fn ($username) => Str::lower((string) $username))
            ->all();

        foreach ($rows as $rowNumber => $row) {
            if (collect($row)->filter()->isEmpty()) {
                continue;
            }

            $row = array_slice($row, 0, count($header));

            if (count($row) !== count($header)) {
                $results['failed'][] = [
                    'row' => $row,
                    'reason' => 'Column mismatch at row ' . ($rowNumber + 2),
                ];
                continue;
            }

            $record = array_combine($header, $row);
            $record = $this->normalizeRecordValues($record);

            $validator = Validator::make($record, [
                'first_name' => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'gender' => ['nullable', 'string', 'max:25'],
                'grade_level' => ['required', 'string', 'max:25'],
                'section' => ['required', 'string', 'max:25'],
            ]);

            if ($validator->fails()) {
                $results['failed'][] = [
                    'row' => $record,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $gradeLevelName = ucwords(strtolower($record['grade_level']));
            $sectionName = ucwords(strtolower($record['section']));

            $gradeLevel = GradeLevel::firstOrCreate(
                ['grade_level' => $gradeLevelName],
                ['status' => 'active']
            );

            $section = Section::firstOrCreate(
                [
                    'section_name' => $sectionName,
                    'grade_level_id' => $gradeLevel->id,
                ],
                ['status' => 'active']
            );

            $match = [
                'first_name' => $record['first_name'],
                'last_name' => $record['last_name'],
            ];

            if (!empty($record['middle_name'])) {
                $match['middle_name'] = $record['middle_name'];
            }

            $learner = Learner::updateOrCreate(
                $match,
                [
                    'first_name' => $record['first_name'],
                    'middle_name' => $record['middle_name'],
                    'last_name' => $record['last_name'],
                    'gender' => $record['gender'],
                    'status' => 'active',
                ]
            );

            $learner->loadMissing('user');

            if (! $learner->user) {
                $usernameBase = $this->buildStudentUsernameBase($record['first_name'], $record['last_name']);
                $username = $this->reserveUniqueUsername($usernameBase, $reservedUsernames);
                $user = $this->createStudentUser($username, $record);
                $learner->user()->associate($user);
                $learner->save();
            }

            Enrollment::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'school_year_id' => $schoolYear->id,
                ],
                [
                    'section_id' => $section->id,
                    'status' => 'active',
                ]
            );

            $results[$learner->wasRecentlyCreated ? 'created' : 'updated'][] = $learner->id;
            $totalProcessed++;
        }

        return response()->json([
            'message' => 'Processed ' . $totalProcessed . ' rows',
            'summary' => $results,
        ]);
    }

    public function bulkUpdateEmails(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv'],
        ]);

        [$header, $rows] = $this->loadSpreadsheetRows($request);

        if (empty($rows)) {
            return response()->json(['error' => 'The uploaded file is empty'], 422);
        }

        $header = array_map(fn ($value) => Str::snake(trim((string) $value)), $header);

        $requiredColumns = [
            'first_name',
            'last_name',
            'email',
        ];

        $missingColumns = array_diff($requiredColumns, $header);
        if (! empty($missingColumns)) {
            return response()->json([
                'error' => 'Missing columns: ' . implode(', ', $missingColumns),
            ], 422);
        }

        $results = ['updated' => [], 'failed' => []];
        $totalProcessed = 0;

        foreach ($rows as $rowNumber => $row) {
            if (collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }

            $row = array_slice($row, 0, count($header));

            if (count($row) !== count($header)) {
                $results['failed'][] = [
                    'row' => $row,
                    'reason' => 'Column mismatch at row ' . ($rowNumber + 2),
                ];
                continue;
            }

            $record = array_combine($header, $row);
            $record = $this->normalizeRecordValues($record);

            $validator = Validator::make($record, [
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255'],
            ]);

            if ($validator->fails()) {
                $results['failed'][] = [
                    'row' => $record,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $firstName = Str::lower($record['first_name']);
            $lastName = Str::lower($record['last_name']);
            $email = Str::lower($record['email']);

            $learners = Learner::with('user')
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName])
                ->get();

            if ($learners->isEmpty()) {
                $results['failed'][] = [
                    'row' => $record,
                    'reason' => 'No learner matched the provided name',
                ];
                continue;
            }

            if ($learners->count() > 1) {
                $results['failed'][] = [
                    'row' => $record,
                    'reason' => 'Multiple learners matched the provided name',
                ];
                continue;
            }

            $learner = $learners->first();

            if (! $learner->user) {
                $results['failed'][] = [
                    'row' => $record,
                    'reason' => 'Matched learner does not have a linked user account',
                ];
                continue;
            }

            $emailInUse = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('id', '!=', $learner->user->id)
                ->exists();

            if ($emailInUse) {
                $results['failed'][] = [
                    'row' => $record,
                    'reason' => 'Email is already assigned to another user',
                ];
                continue;
            }

            DB::transaction(function () use ($learner, $email) {
                $learner->email = $email;
                $learner->save();

                $learner->user->email = $email;
                $learner->user->save();
            });

            $results['updated'][] = [
                'learner_id' => $learner->id,
                'email' => $email,
            ];
            $totalProcessed++;
        }

        return response()->json([
            'message' => 'Processed ' . $totalProcessed . ' rows',
            'summary' => $results,
        ]);
    }

    private function loadSpreadsheetRows(Request $request): array
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = array_map(
            fn ($row) => array_values($row),
            $worksheet->toArray(null, true, true, true)
        );

        if (empty($rows)) {
            return [[], []];
        }

        $header = array_shift($rows);

        return [$header, $rows];
    }

    private function normalizeRecordValues(array $record): array
    {
        return array_map(
            fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value)),
            $record
        );
    }

    private function buildStudentUsernameBase(string $firstName, string $lastName): string
    {
        $nameParts = array_values(array_filter(preg_split('/\s+/', trim($firstName))));
        $initials = '';

        if (isset($nameParts[0]) && $nameParts[0] !== '') {
            $initials .= Str::substr($nameParts[0], 0, 1);
        }

        if (isset($nameParts[1]) && $nameParts[1] !== '') {
            $initials .= Str::substr($nameParts[1], 0, 1);
        }

        $lastNameSlug = Str::slug($lastName, '');

        if ($lastNameSlug === '') {
            $lastNameSlug = 'student';
        }

        return Str::lower($lastNameSlug . $initials);
    }

    private function reserveUniqueUsername(string $base, array &$reserved): string
    {
        $seed = $base !== '' ? $base : 'student';
        $username = $seed;
        $counter = 1;

        while (in_array($username, $reserved, true)) {
            $username = $seed . $counter;
            $counter++;
        }

        $reserved[] = $username;

        return $username;
    }

    private function createStudentUser(string $username, array $record): User
    {
        $payload = [
            'name' => $this->buildStudentFullName($record),
            'username' => $username,
            'email' => $this->buildStudentEmail($username),
            'password' => Hash::make("{$username}12345"),
        ];

        $user = User::create($payload);

        $user->forceFill([
            'role' => 'student',
            'status' => 'active',
        ])->save();

        return $user;
    }

    private function buildStudentFullName(array $record): string
    {
        $parts = array_filter([
            $record['first_name'] ?? null,
            $record['middle_name'] ?? null,
            $record['last_name'] ?? null,
        ]);

        return trim(implode(' ', $parts));
    }

    private function buildStudentEmail(string $username): string
    {
        return Str::lower("{$username}@students.pshs.edu.ph");
    }
}
