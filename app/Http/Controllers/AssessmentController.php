<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentRequest;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\SchoolYear;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

    public function store(AssessmentRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['perfect_score'] = $data['perfect_score'] ?? 100;

        $learnerScores = collect($data['learner_scores'] ?? []);
        unset($data['learner_scores']);

        $assessment = Assessment::create($data);

        if ($learnerScores->isNotEmpty()) {
            $assessment->learners()->sync($this->pivotPayload($learnerScores));
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

    public function update(AssessmentRequest $request, Assessment $assessment)
    {
        $data = $request->validated();
        $learnerScores = $data['learner_scores'] ?? null;
        unset($data['learner_scores']);

        $data['perfect_score'] = $data['perfect_score'] ?? 100;

        $assessment->update($data);

        // Non-destructive: an empty payload is a no-op, and learners outside the
        // submitted set keep their existing scores instead of being detached.
        if (is_array($learnerScores) && $learnerScores !== []) {
            $assessment->learners()->syncWithoutDetaching($this->pivotPayload(collect($learnerScores)));
        }

        $assessment->load($this->loadRelations());

        if ($request->wantsJson()) {
            return response()->json($assessment);
        }

        return redirect()->route('assessments.index')->with('success', 'Assessment updated');
    }

    public function destroy(Request $request, Assessment $assessment)
    {
        $assessment->delete();

        if ($request->wantsJson()) {
            return response()->json(null, 204);
        }

        return redirect()->route('assessments.index')->with('success', 'Assessment deleted');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $learnerScores
     * @return array<int, array<string, mixed>>
     */
    private function pivotPayload($learnerScores): array
    {
        return $learnerScores->mapWithKeys(fn ($item) => [
            $item['learner_id'] => [
                'score' => $item['score'] ?? 0,
                'tentative' => (bool) ($item['tentative'] ?? false),
            ],
        ])->toArray();
    }
}
