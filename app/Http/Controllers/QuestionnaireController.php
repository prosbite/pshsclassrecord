<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionnaireRequest;
use App\Models\Question;
use App\Models\Questionnaire;
use App\Models\Topic;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuestionnaireController extends Controller
{
    public function index(Request $request)
    {
        $topicFilter = $request->query('topic');

        $questionnaires = Questionnaire::with('topic')
            ->withCount('questions')
            ->when($topicFilter, fn ($query) => $query->where('topic_id', $topicFilter))
            ->orderBy('topic_id')
            ->orderBy('position')
            ->orderBy('title')
            ->get();

        return Inertia::render('Questionnaires/Index', [
            'questionnaires' => $questionnaires,
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'topicFilter' => $topicFilter ? (int) $topicFilter : null,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Questionnaires/Create', [
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'selectedTopicId' => $request->query('topic') ? (int) $request->query('topic') : null,
        ]);
    }

    public function store(QuestionnaireRequest $request)
    {
        $data = $request->validated();
        $questionIds = $data['question_ids'] ?? [];
        unset($data['question_ids']);

        $questionnaire = Questionnaire::create($data);

        $questionnaire->questions()->sync($this->positionPayload($questionIds));

        return redirect()->route('questionnaires.index')->with('success', 'Questionnaire created');
    }

    public function edit(Questionnaire $questionnaire)
    {
        $questionnaire->load(['topic', 'questions']);

        $attachedIds = $questionnaire->questions->pluck('id');

        return Inertia::render('Questionnaires/Edit', [
            'questionnaire' => $questionnaire,
            'topics' => Topic::orderBy('name')->get(['id', 'name']),
            'availableQuestions' => Question::query()
                ->where('topic_id', $questionnaire->topic_id)
                ->whereNotIn('id', $attachedIds->all())
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'topic_id', 'type', 'prompt_text', 'image_path', 'points']),
        ]);
    }

    public function update(QuestionnaireRequest $request, Questionnaire $questionnaire)
    {
        $data = $request->validated();
        $questionIds = $data['question_ids'] ?? null;
        unset($data['question_ids']);

        $questionnaire->update($data);

        if ($request->has('question_ids')) {
            $questionnaire->questions()->sync($this->positionPayload($questionIds ?? []));
        }

        return redirect()->route('questionnaires.index')->with('success', 'Questionnaire updated');
    }

    public function destroy(Questionnaire $questionnaire)
    {
        $questionnaire->delete();

        return redirect()->route('questionnaires.index')->with('success', 'Questionnaire deleted');
    }

    /**
     * Build a sync payload that stores each id's array order as its pivot position.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array<string, int>>
     */
    private function positionPayload(array $ids): array
    {
        return collect($ids)
            ->values()
            ->mapWithKeys(fn ($id, $index) => [(int) $id => ['position' => $index]])
            ->toArray();
    }
}
