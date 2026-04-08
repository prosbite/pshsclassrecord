<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Http\Request;
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

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = array_map(
            fn ($row) => array_values($row),
            $worksheet->toArray(null, true, true, true)
        );

        if (empty($rows)) {
            return response()->json(['error' => 'The uploaded file is empty'], 422);
        }

        $header = array_shift($rows);
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
            $record = array_map(fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value)), $record);

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
}
