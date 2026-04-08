<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    protected function loadRelations(): array
    {
        return ['assessmentType', 'schoolYear', 'quarter', 'section', 'user', 'learners'];
    }

    public function index()
    {
        $assessments = Assessment::with($this->loadRelations())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assessments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'assessment_type_id' => ['required', 'exists:assessment_types,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'perfect_score' => ['nullable', 'integer', 'min:0'],
            'assessment_date' => ['required', 'date'],
            'learner_scores' => ['nullable', 'array'],
            'learner_scores.*.learner_id' => ['required', 'exists:learners,id'],
            'learner_scores.*.score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['user_id'] = $data['user_id'] ?? $request->user()?->id;
        $data['perfect_score'] = $data['perfect_score'] ?? 100;

        if (! $data['user_id']) {
            return response()->json(['error' => 'Unable to resolve user'], 422);
        }

        $learnerScores = collect($data['learner_scores'] ?? []);
        unset($data['learner_scores']);

        $assessment = Assessment::create($data);

        if ($learnerScores->isNotEmpty()) {
            $pivot = $learnerScores->mapWithKeys(fn ($item) => [
                $item['learner_id'] => [
                    'score' => $item['score'] ?? 0,
                ],
            ]);

            $assessment->learners()->sync($pivot->toArray());
        }

        $assessment->load($this->loadRelations());

        if ($request->wantsJson()) {
            return response()->json($assessment, 201);
        }

        return redirect()->route('assessments.index')->with('success', 'Assessment created');
    }

    public function sectionLearners(Request $request)
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $schoolYear = SchoolYear::current();

        $enrollments = Enrollment::with('learner')
            ->where('enrollments.section_id', $data['section_id'])
            ->when($schoolYear, fn ($query) => $query->where('enrollments.school_year_id', $schoolYear->id))
            ->where('enrollments.status', 'active')
            ->join('learners', 'learners.id', '=', 'enrollments.learner_id')
            ->orderBy('learners.last_name')
            ->select('enrollments.*')
            ->get();

        return response()->json($enrollments->map(fn ($enrollment) => [
            'id' => $enrollment->id,
            'learner_id' => $enrollment->learner_id,
            'learner' => $enrollment->learner,
            'status' => $enrollment->status,
        ]));
    }

    public function show(Assessment $assessment)
    {
        return response()->json($assessment->load($this->loadRelations()));
    }

    public function update(Request $request, Assessment $assessment)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'assessment_type_id' => ['sometimes', 'exists:assessment_types,id'],
            'school_year_id' => ['sometimes', 'exists:school_years,id'],
            'quarter_id' => ['sometimes', 'exists:quarters,id'],
            'section_id' => ['sometimes', 'exists:sections,id'],
            'user_id' => ['sometimes', 'exists:users,id'],
            'perfect_score' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (empty($data['user_id'] ?? null) && $request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        $assessment->update($data);

        return response()->json($assessment->load($this->loadRelations()));
    }

    public function destroy(Assessment $assessment)
    {
        $assessment->delete();

        return response()->json(null, 204);
    }
}
