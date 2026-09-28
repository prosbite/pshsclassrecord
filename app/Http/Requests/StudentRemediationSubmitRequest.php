<?php

namespace App\Http\Requests;

use App\Models\ExerciseSession;
use App\Models\ExerciseSessionQuestionOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StudentRemediationSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExerciseSession|null $session */
        $session = $this->route('exerciseSession');
        $user = $this->user();

        if (! $session || ! $user) {
            return false;
        }

        return $session->learner?->user_id === $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array'],
            'answers.*.session_question_id' => ['required', 'integer'],
            'answers.*.selected_option_id' => ['nullable', 'integer'],
            'answers.*.response_text' => ['nullable', 'string'],
        ];
    }

    /**
     * Answers may only target snapshot rows that belong to the session, and a
     * selected option must belong to that same snapshot question.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rows = $this->input('answers');

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

                $owned = $submitted->isEmpty()
                    ? collect()
                    : $session->sessionQuestions()
                        ->whereIn('id', $submitted->all())
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id);

                foreach ($rows as $index => $row) {
                    if (! isset($row['session_question_id'])) {
                        continue;
                    }

                    $questionId = (int) $row['session_question_id'];

                    if (! $owned->contains($questionId)) {
                        $validator->errors()->add(
                            "answers.{$index}.session_question_id",
                            'The selected question does not belong to this session.'
                        );

                        continue;
                    }

                    $optionId = $row['selected_option_id'] ?? null;

                    if ($optionId === null || $optionId === '') {
                        continue;
                    }

                    $belongs = ExerciseSessionQuestionOption::query()
                        ->whereKey((int) $optionId)
                        ->where('exercise_session_question_id', $questionId)
                        ->exists();

                    if (! $belongs) {
                        $validator->errors()->add(
                            "answers.{$index}.selected_option_id",
                            'The selected option does not belong to this question.'
                        );
                    }
                }
            },
        ];
    }
}
