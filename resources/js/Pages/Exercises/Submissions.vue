<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    tab: {
        type: String,
        default: 'submitted',
    },
    search: {
        type: String,
        default: '',
    },
    counts: {
        type: Object,
        default: () => ({ submitted: 0, assigned: 0 }),
    },
    sessions: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
});

const searchInput = ref(props.search);
let searchTimer = null;

const kindLabel = (kind) => (kind === 'enhancement' ? 'Enhancement' : 'Preventive');

const kindClasses = (kind) => ({
    preventive: 'bg-indigo-100 text-indigo-700',
    enhancement: 'bg-emerald-100 text-emerald-700',
}[kind] ?? 'bg-slate-100 text-slate-600');

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
};

const visitList = (overrides = {}) => {
    router.get(route('exercises.submissions.index'), {
        tab: props.tab,
        search: searchInput.value,
        ...overrides,
    }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const commitSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    visitList();
};

const pushSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        visitList();
    }, 300);
};

const selectTab = (tab) => {
    if (tab === props.tab) {
        return;
    }

    visitList({ tab });
};

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

const rows = computed(() => props.sessions.data ?? []);
const links = computed(() => props.sessions.links ?? []);
</script>

<template>
    <Head title="Exercise Submissions" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercises</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Exercise Submissions</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Submitted and not-yet-answered exercise sessions for the current school year.
                        </p>
                    </div>

                    <label class="block w-full lg:max-w-sm">
                        <span class="sr-only">Search by learner name</span>
                        <input
                            v-model="searchInput"
                            type="text"
                            placeholder="Search by learner name"
                            class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-2.5 text-sm text-white placeholder:text-slate-300 focus:border-white/40 focus:outline-none"
                            @input="pushSearch"
                            @keyup.enter="commitSearch"
                        />
                    </label>
                </div>
            </section>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                        :class="tab === 'submitted'
                            ? 'bg-slate-900 text-white shadow'
                            : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                        @click="selectTab('submitted')"
                    >
                        Awaiting marking
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            :class="tab === 'submitted' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ counts.submitted ?? 0 }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                        :class="tab === 'assigned'
                            ? 'bg-slate-900 text-white shadow'
                            : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                        @click="selectTab('assigned')"
                    >
                        Did not answer yet
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            :class="tab === 'assigned' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ counts.assigned ?? 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div v-if="!rows.length" class="px-6 py-10 text-center text-sm text-slate-500">
                    <template v-if="tab === 'submitted'">Nothing is waiting to be marked.</template>
                    <template v-else>Every assigned exercise has been answered.</template>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Learner</th>
                                <th class="px-6 py-3 font-semibold text-center">Kind</th>
                                <th class="px-6 py-3 font-semibold">Assessment</th>
                                <th class="px-6 py-3 font-semibold">Section</th>
                                <th class="px-6 py-3 font-semibold text-center">Quarter</th>
                                <th class="px-6 py-3 font-semibold text-center">Answered</th>
                                <th class="px-6 py-3 font-semibold">{{ tab === 'submitted' ? 'Submitted' : 'Given' }}</th>
                                <th class="px-6 py-3 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="session in rows"
                                :key="session.id"
                                class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                            >
                                <td class="px-6 py-4 font-semibold text-slate-900">
                                    <template v-if="session.learner?.name">{{ session.learner.name }}</template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                        :class="kindClasses(session.kind)"
                                    >
                                        {{ kindLabel(session.kind) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    {{ session.assessment_title || session.type || 'Exercise' }}
                                </td>
                                <td class="px-6 py-4 text-slate-700">{{ session.section || '—' }}</td>
                                <td class="px-6 py-4 text-center text-slate-700">
                                    {{ session.quarter ? `Q${session.quarter}` : '—' }}
                                </td>
                                <td class="px-6 py-4 text-center text-slate-700">
                                    {{ session.answered_count }} / {{ session.total_questions }}
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    {{ tab === 'submitted'
                                        ? formatDateTime(session.submitted_at)
                                        : formatDateTime(session.created_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="route('exercise-sessions.show', session.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Open
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="links.length > 3" class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-6 py-5">
                    <template v-for="(link, index) in links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                            :class="link.active ? 'bg-indigo-600 text-white shadow' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-full px-4 py-1.5 text-xs font-semibold text-slate-300"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </MainAuthLayout>
</template>
