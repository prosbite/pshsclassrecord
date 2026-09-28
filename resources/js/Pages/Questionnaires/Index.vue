<script setup>
import { Link, router } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    questionnaires: {
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
});

const applyFilter = (event) => {
    const value = event.target.value;
    router.get(route('questionnaires.index'), value ? { topic: value } : {}, { preserveState: true });
};

const confirmDelete = (questionnaire) => {
    if (!window.confirm(`Delete the questionnaire "${questionnaire.title}"?`)) {
        return;
    }

    router.delete(route('questionnaires.destroy', questionnaire.id), { preserveScroll: true });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                    <h1 class="text-2xl font-semibold text-slate-900">Questionnaires</h1>
                    <p class="text-sm text-slate-500">Each questionnaire belongs to one topic.</p>
                </div>
                <Link
                    :href="route('questionnaires.create')"
                    class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                >
                    New questionnaire
                </Link>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
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
            </div>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div v-if="!questionnaires.length" class="px-6 py-10 text-center text-sm text-slate-500">
                    No questionnaires found.
                </div>
                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold">Topic</th>
                            <th class="px-6 py-3 font-semibold text-center">Questions</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="questionnaire in questionnaires"
                            :key="questionnaire.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ questionnaire.title }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ questionnaire.topic?.name || '—' }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ questionnaire.questions_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <Link
                                        :href="route('questionnaires.edit', questionnaire.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="confirmDelete(questionnaire)"
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
