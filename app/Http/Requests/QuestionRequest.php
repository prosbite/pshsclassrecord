<?php

namespace App\Http\Requests;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the question type so downstream rules and the controller can
     * rely on it. Omitted values fall back to the existing row (updates) or
     * `text` (creates).
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('type')) {
            return;
        }

        /** @var Question|null $question */
        $question = $this->route('question');

        $this->merge([
            'type' => $question?->type ?? Question::TYPE_TEXT,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'exists:topics,id'],
            'type' => ['required', 'in:multiple_choice,text'],
            'prompt_text' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'points' => ['required', 'integer', 'min:1'],
            'answer_key' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
            'options' => ['exclude_if:type,text', 'required_if:type,multiple_choice', 'array', 'min:2', 'max:10'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.is_correct' => ['boolean'],
        ];
    }

    /**
     * A question must carry either prompt text or an image (existing or newly
     * uploaded), and a multiple-choice question must mark exactly one option
     * as correct.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasText = trim((string) $this->input('prompt_text')) !== '';
                $hasUpload = $this->hasFile('image');

                /** @var Question|null $question */
                $question = $this->route('question');
                $hasExistingImage = $question?->image_path !== null;

                if (! $hasText && ! $hasUpload && ! $hasExistingImage) {
                    $validator->errors()->add(
                        'image',
                        'Provide prompt text or upload an image for the question.'
                    );
                }

                if ($this->input('type') !== Question::TYPE_MULTIPLE_CHOICE) {
                    return;
                }

                $correct = collect($this->input('options', []))
                    ->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->count();

                if ($correct !== 1) {
                    $validator->errors()->add('options', 'Mark exactly one option as the correct answer.');
                }
            },
        ];
    }
}
