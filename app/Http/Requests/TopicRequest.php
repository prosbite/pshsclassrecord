<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopicRequest extends FormRequest
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
        $topicId = $this->route('topic')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('topics', 'name')->ignore($topicId),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}
