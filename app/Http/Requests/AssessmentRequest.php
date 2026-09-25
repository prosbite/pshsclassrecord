<?php

namespace App\Http\Requests;

use App\Models\Learner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'assessment_type_id' => ['required', 'exists:assessment_types,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'quarter_id' => ['required', 'exists:quarters,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'perfect_score' => ['nullable', 'integer', 'min:0'],
            'assessment_date' => ['required', 'date'],
            'learner_scores' => ['nullable', 'array'],
            'learner_scores.*.learner_id' => ['required', 'integer'],
            'learner_scores.*.score' => ['nullable', 'numeric', 'min:0'],
            'learner_scores.*.tentative' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validate learner ids set-wise so a full-class save runs a single query
     * instead of one `exists` query per submitted row.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rows = $this->input('learner_scores');

                if (! is_array($rows) || $rows === []) {
                    return;
                }

                $submittedIds = collect($rows)
                    ->pluck('learner_id')
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($submittedIds->isEmpty()) {
                    return;
                }

                $existingIds = Learner::query()
                    ->whereIn('id', $submittedIds->all())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $missing = $submittedIds->diff($existingIds)->all();

                foreach ($rows as $index => $row) {
                    if (! isset($row['learner_id'])) {
                        continue;
                    }

                    if (in_array((int) $row['learner_id'], $missing, true)) {
                        $validator->errors()->add(
                            "learner_scores.{$index}.learner_id",
                            'The selected learner is invalid.'
                        );
                    }
                }
            },
        ];
    }
}
