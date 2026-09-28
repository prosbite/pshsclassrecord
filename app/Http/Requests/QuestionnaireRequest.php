<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuestionnaireRequest extends FormRequest
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
            'topic_id' => ['required', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['integer'],
        ];
    }

    /**
     * A questionnaire may only include questions that belong to its own topic.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ids = $this->input('question_ids');

                if (! is_array($ids) || $ids === [] || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $topicId = (int) $this->input('topic_id');

                $submitted = collect($ids)
                    ->filter(fn ($id) => $id !== null && $id !== '')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($submitted->isEmpty()) {
                    return;
                }

                $questions = Question::query()
                    ->whereIn('id', $submitted->all())
                    ->get(['id', 'topic_id'])
                    ->keyBy('id');

                foreach ($ids as $index => $id) {
                    $question = $questions->get((int) $id);

                    if (! $question) {
                        $validator->errors()->add(
                            "question_ids.{$index}",
                            'The selected question is invalid.'
                        );

                        continue;
                    }

                    if ((int) $question->topic_id !== $topicId) {
                        $validator->errors()->add(
                            "question_ids.{$index}",
                            'The question must belong to the selected topic.'
                        );
                    }
                }
            },
        ];
    }
}
