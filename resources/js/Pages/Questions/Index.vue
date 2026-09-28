<script setup>
import { Link, router } from '@inertiajs/vue3';
import MathText from '@/Components/Exercises/MathText.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    questions: {
        type: Array,
        default: () => [],
    },
    topics: {
        type: Array,
        default: () => [],
    },
    topicFilter: {
        type: Number,
        default: null,
    },
    typeFilter: {
        type: String,
        default: null,
    },
});

const applyFilter = (event) => {
    const value = event.target.value;
    const query = {};

    if (value) {
        query.topic = value;
    }

    if (props.typeFilter) {
        query.type = props.typeFilter;
    }

    router.get(route('questions.index'), query, { preserveState: true });
};

const applyTypeFilter = (event) => {
    const value = event.target.value;
    const query = {};

    if (props.topicFilter) {
        query.topic = props.topicFilter;
    }

    if (value) {
        query.type = value;
    }

    router.get(route('questions.index'), query, { preserveState: true });
};

const confirmDelete = (question) => {
    if (!window.confirm('Delete this question? Frozen session copies keep their content and scores.')) {
        return;
    }

    router.delete(route('questions.destroy', question.id), { preserveScroll: true });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                    <h1 class="text-2xl font-semibold text-slate-900">Questions</h1>
                    <p class="text-sm text-slate-500">Each question belongs to one topic and can be reused across questionnaires.</p>
                </div>
                <Link
                    :href="route('questions.create', topicFilter ? { topic: topicFilter } : {})"
                    class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                >
                    New question
                </Link>
            </div>

            <div class="grid gap-4 rounded-3xl bg-white p-6 shadow-lg sm:grid-cols-2">
                <label class="block max-w-sm">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Filter by topic</span>
                    <select
                        :value="topicFilter ?? ''"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        @change="applyFilter"
                    >
                        <option value="">All topics</option>
                        <option v-for="topic in topics" :key="topic.id" :value="topic.id">{{ topic.name }}</option>
                    </select>
                </label>

                <label class="block max-w-sm">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Filter by type</span>
                    <select
                        :value="typeFilter ?? ''"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        @change="applyTypeFilter"
                    >
                        <option value="">All types</option>
                        <option value="multiple_choice">Multiple choice</option>
                        <option value="text">Text</option>
                    </select>
                </label>
            </div>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div v-if="!questions.length" class="px-6 py-10 text-center text-sm text-slate-500">
                    No questions found.
                </div>
                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Question</th>
                            <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 font-semibold">Topic</th>
                            <th class="px-6 py-3 font-semibold text-center">Points</th>
                            <th class="px-6 py-3 font-semibold text-center">Options</th>
                            <th class="px-6 py-3 font-semibold text-center">In questionnaires</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="question in questions"
                            :key="question.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="question.image_url"
                                        :src="question.image_url"
                                        class="h-10 w-10 rounded-lg object-cover"
                                        alt="Question image"
                                    />
                                    <MathText
                                        v-if="question.prompt_text"
                                        :content="question.prompt_text"
                                        class="font-semibold text-slate-900"
                                    />
                                    <span v-else class="font-semibold text-slate-900">(image only)</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                    :class="question.type === 'multiple_choice' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ question.type === 'multiple_choice' ? 'MCQ' : 'Text' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ question.topic?.name || '—' }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ question.points }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">
                                {{ question.type === 'multiple_choice' ? (question.options_count ?? 0) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ question.questionnaires_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <Link
                                        :href="route('questions.edit', question.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="confirmDelete(question)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainAuthLayout>
</template>
