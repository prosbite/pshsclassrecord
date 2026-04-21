<?php

namespace App\Http\Controllers;

use App\Models\QuarterlyAssessment;
use Illuminate\Http\Request;

class QuarterlyAssessmentController extends Controller
{
    protected function loadRelations(): array
    {
        return ['schoolYear', 'quarter', 'section', 'user'];
    }

    public function index()
    {
        $assessments = QuarterlyAssessment::with($this->loadRelations())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($assessments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'assessment' => ['required', 'array'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'section_id' => ['required', 'exists:sections,id'],
        ]);

        $userId = $request->user()?->id;

        if (! $userId) {
            return response()->json(['error' => 'Unable to resolve user'], 422);
        }

        $data['user_id'] = $userId;

        $assessment = QuarterlyAssessment::create($data);

        return response()->json($assessment->load($this->loadRelations()), 201);
    }

    public function show(QuarterlyAssessment $quarterlyAssessment)
    {
        return response()->json($quarterlyAssessment->load($this->loadRelations()));
    }

    public function update(Request $request, QuarterlyAssessment $quarterlyAssessment)
    {
        $data = $request->validate([
            'assessment' => ['sometimes', 'array'],
            'school_year_id' => ['sometimes', 'exists:school_years,id'],
            'quarter_id' => ['sometimes', 'exists:quarters,id'],
            'section_id' => ['sometimes', 'exists:sections,id'],
            'user_id' => ['sometimes', 'exists:users,id'],
        ]);

        $quarterlyAssessment->update($data);

        if ($request->expectsJson()) {
            return response()->json($quarterlyAssessment->load($this->loadRelations()));
        }

        return redirect()
            ->route('quarterly-assessments.show', $quarterlyAssessment)
            ->with('success', 'Quarterly assessment updated');
    }

    public function destroy(QuarterlyAssessment $quarterlyAssessment)
    {
        $quarterlyAssessment->delete();

        return response()->json(null, 204);
    }
}
