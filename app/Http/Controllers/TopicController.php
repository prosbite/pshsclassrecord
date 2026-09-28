<?php

namespace App\Http\Controllers;

use App\Http\Requests\TopicRequest;
use App\Models\Topic;
use Inertia\Inertia;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Topic::withCount(['questionnaires', 'questions'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Topics/Index', [
            'topics' => $topics,
        ]);
    }

    public function create()
    {
        return Inertia::render('Topics/Create');
    }

    public function store(TopicRequest $request)
    {
        Topic::create($request->validated());

        return redirect()->route('topics.index')->with('success', 'Topic created');
    }

    public function edit(Topic $topic)
    {
        $topic->load(['questionnaires' => fn ($query) => $query->withCount('questions')]);

        return Inertia::render('Topics/Edit', [
            'topic' => $topic,
        ]);
    }

    public function update(TopicRequest $request, Topic $topic)
    {
        $topic->update($request->validated());

        return redirect()->route('topics.index')->with('success', 'Topic updated');
    }

    public function destroy(Topic $topic)
    {
        if ($topic->questionnaires()->exists()) {
            return redirect()
                ->route('topics.index')
                ->withErrors(['topic' => 'Remove this topic\'s questionnaires before deleting it.']);
        }

        $topic->delete();

        return redirect()->route('topics.index')->with('success', 'Topic deleted');
    }
}
