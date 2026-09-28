<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    topics: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const topicError = computed(() => page.props.errors?.topic ?? '');

const confirmDelete = (topic) => {
    if (!window.confirm(`Delete the topic "${topic.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(route('topics.destroy', topic.id), { preserveScroll: true });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                    <h1 class="text-2xl font-semibold text-slate-900">Topics</h1>
                    <p class="text-sm text-slate-500">
                        Organize questionnaires under topics. Topics only help select a pool; they never decide it.
                    </p>
                </div>
                <Link
                    :href="route('topics.create')"
                    class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                >
                    New topic
                </Link>
            </div>

            <p
                v-if="topicError"
                class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-600"
            >
                {{ topicError }}
            </p>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div v-if="!topics.length" class="px-6 py-10 text-center text-sm text-slate-500">
                    No topics yet. Create one to start building questionnaires.
                </div>
                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Name</th>
                            <th class="px-6 py-3 font-semibold">Description</th>
                            <th class="px-6 py-3 font-semibold text-center">Questionnaires</th>
                            <th class="px-6 py-3 font-semibold text-center">Questions</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="topic in topics"
                            :key="topic.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ topic.name }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ topic.description || '—' }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ topic.questionnaires_count }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ topic.questions_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <Link
                                        :href="route('questions.index', { topic: topic.id })"
                                        class="text-xs font-semibold uppercase tracking-widest text-slate-600 hover:text-slate-800"
                                    >
                                        Questions
                                    </Link>
                                    <Link
                                        :href="route('topics.edit', topic.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="confirmDelete(topic)"
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
