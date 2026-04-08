<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LearnerController extends Controller
{
    public function index(Request $request)
    {
        $gradeLevelName = $request->query('grade_level', '');
        $sectionName = $request->query('section', '');
        $search = $request->query('search', '');

        if (! $request->has('grade_level') && ! $request->has('section')) {
            $gradeLevelName = 'Grade 12';
            $sectionName = 'Del Mundo';
        }

        $enrollments = Enrollment::with(['learner', 'section.gradeLevel', 'schoolYear'])
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->when($sectionName, function ($query) use ($sectionName) {
                $query->whereHas('section', function ($section) use ($sectionName) {
                    $section->where('section_name', $sectionName);
                });
            })
            ->when($gradeLevelName, function ($query) use ($gradeLevelName) {
                $query->whereHas('section.gradeLevel', function ($grade) use ($gradeLevelName) {
                    $grade->where('grade_level', $gradeLevelName);
                });
            })
            ->when($search, function ($query) use ($search) {
                $query->whereHas('learner', function ($learner) use ($search) {
                    $learner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('learners.last_name')
            ->select('enrollments.*')
            ->get();

        $gradeLevels = GradeLevel::with('sections')->orderBy('grade_level')->get();

        return Inertia::render('Students/Index', [
            'enrollments' => $enrollments,
            'gradeLevels' => $gradeLevels,
            'filters' => [
                'grade_level' => $gradeLevelName,
                'section' => $sectionName,
                'search' => $search,
            ],
        ]);
    }
}
