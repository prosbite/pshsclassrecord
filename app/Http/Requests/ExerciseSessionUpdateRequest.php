<?php

namespace App\Http\Requests;

use App\Models\ExerciseSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExerciseSessionUpdateRequest extends FormRequest
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
            'questionnaire_ids' => ['nullable', 'array'],
            'questionnaire_ids.*' => ['integer', 'exists:questionnaires,id'],
            'scores' => ['nullable', 'array'],
            'scores.*.session_question_id' => ['required', 'integer'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'status' => ['nullable', 'in:assigned,submitted,completed'],
        ];
    }

    /**
     * Scores may only target snapshot rows that belong to the session being updated.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rows = $this->input('scores');

                if (! is_array($rows) || $rows === []) {
                    return;
                }

                /** @var ExerciseSession|null $session */
                $session = $this->route('exerciseSession');

                if (! $session) {
                    return;
                }

                $submitted = collect($rows)
                    ->pluck('session_question_id')
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($submitted->isEmpty()) {
                    return;
                }

                $owned = $session->sessionQuestions()
                    ->whereIn('id', $submitted->all())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $missing = $submitted->diff($owned)->all();

                foreach ($rows as $index => $row) {
                    if (! isset($row['session_question_id'])) {
                        continue;
                    }

                    if (in_array((int) $row['session_question_id'], $missing, true)) {
                        $validator->errors()->add(
                            "scores.{$index}.session_question_id",
                            'The selected question does not belong to this session.'
                        );
                    }
                }
            },
        ];
    }
}
