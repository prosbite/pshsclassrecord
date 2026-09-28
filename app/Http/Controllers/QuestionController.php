<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionRequest;
use App\Models\ExerciseSessionQuestion;
use App\Models\Question;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $topicFilter = $request->query('topic');
        $typeFilter = $request->query('type');

        $questions = Question::with('topic')
            ->withCount(['questionnaires', 'options'])
            ->when($topicFilter, fn ($query) => $query->where('topic_id', $topicFilter))
            ->when(
                in_array($typeFilter, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TEXT], true),
                fn ($query) => $query->where('type', $typeFilter)
            )
            ->orderBy('topic_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return Inertia::render('Questions/Index', [
            'questions' => $questions,
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'topicFilter' => $topicFilter ? (int) $topicFilter : null,
            'typeFilter' => in_array($typeFilter, [Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TEXT], true)
                ? $typeFilter
                : null,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Questions/Create', [
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'selectedTopicId' => $request->query('topic') ? (int) $request->query('topic') : null,
        ]);
    }

    public function store(QuestionRequest $request)
    {
        $data = $request->validated();
        $options = $data['options'] ?? [];
        unset($data['image'], $data['options']);

        $data['type'] ??= Question::TYPE_TEXT;

        if ($data['type'] === Question::TYPE_MULTIPLE_CHOICE) {
            $data['answer_key'] = null;
        }

        if (! isset($data['position'])) {
            $data['position'] = ((int) Question::query()
                ->where('topic_id', $data['topic_id'])
                ->max('position')) + 1;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('questions', 'public');
        }

        $question = Question::create($data);

        if ($data['type'] === Question::TYPE_MULTIPLE_CHOICE) {
            $this->syncOptions($question, $options);
        }

        return redirect()->route('questions.index')->with('success', 'Question created');
    }

    public function edit(Question $question)
    {
        $question->load('topic', 'options');

        return Inertia::render('Questions/Edit', [
            'question' => $question,
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(QuestionRequest $request, Question $question)
    {
        $data = $request->validated();
        $options = $data['options'] ?? [];
        unset($data['image'], $data['options']);

        $data['type'] ??= $question->type ?? Question::TYPE_TEXT;

        if ($data['type'] === Question::TYPE_MULTIPLE_CHOICE) {
            // The correct answer is the marked option, never free text.
            $data['answer_key'] = null;
        }

        $oldPath = $question->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('questions', 'public');
        }

        $question->update($data);

        if ($data['type'] === Question::TYPE_MULTIPLE_CHOICE) {
            $this->syncOptions($question, $options);
        } else {
            $question->options()->delete();
        }

        if ($oldPath && $oldPath !== $question->image_path) {
            $this->deleteImageIfUnused($oldPath);
        }

        return redirect()->route('questions.index')->with('success', 'Question updated');
    }

    public function destroy(Question $question)
    {
        $imagePath = $question->image_path;

        $question->delete();

        if ($imagePath) {
            $this->deleteImageIfUnused($imagePath);
        }

        return redirect()->route('questions.index')->with('success', 'Question deleted');
    }

    /**
     * Reconcile the bank options with the submitted array order: existing rows
     * (matched by id, scoped to the question) update in place, new rows are
     * created, and any option missing from the payload is removed.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    private function syncOptions(Question $question, array $options): void
    {
        $existing = $question->options()->get()->keyBy('id');
        $keptIds = [];

        foreach (array_values($options) as $position => $option) {
            $payload = [
                'position' => $position,
                'label' => $option['label'],
                'is_correct' => (bool) ($option['is_correct'] ?? false),
            ];

            $id = isset($option['id']) ? (int) $option['id'] : null;
            $row = $id !== null ? $existing->get($id) : null;

            if ($row) {
                $row->update($payload);
            } else {
                $row = $question->options()->create($payload);
            }

            $keptIds[] = $row->id;
        }

        $question->options()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Snapshot rows share the same stored path string, so a file may only be
     * removed once no frozen session copy still references it.
     */
    private function deleteImageIfUnused(string $path): void
    {
        if (ExerciseSessionQuestion::query()->where('image_path', $path)->exists()) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
