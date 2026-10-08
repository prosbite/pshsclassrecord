<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            school_year: null,
            active_learners: 0,
            sections: 0,
            assessments: 0,
            passing_threshold: 75,
        }),
    },
    counts: {
        type: Object,
        default: () => ({ topics: 0, questionnaires: 0, questions: 0 }),
    },
    remediation: {
        type: Object,
        default: () => ({ assigned: 0, submitted: 0, completed: 0, pending: [] }),
    },
    recentAssessments: { type: Array, default: () => [] },
    recentLogins: { type: Array, default: () => [] },
});

const pending = computed(() => props.remediation.pending ?? []);
const awaitingCount = computed(() => props.remediation.submitted ?? 0);

const kindLabel = (kind) => (kind === 'enhancement' ? 'Enhancement' : 'Preventive');

const kindClasses = (kind) => (kind === 'enhancement'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-indigo-100 text-indigo-700');

const threshold = computed(() => Number(props.summary.passing_threshold ?? 0));

const statCards = computed(() => [
    {
        label: 'Active Learners',
        value: props.summary.active_learners ?? 0,
        hint: props.summary.school_year ? `School year ${props.summary.school_year}` : 'No active school year',
        accent: 'text-slate-900',
        href: route('students'),
    },
    {
        label: 'Sections',
        value: props.summary.sections ?? 0,
        hint: 'All grade levels',
        accent: 'text-slate-900',
        href: route('students'),
    },
    {
        label: 'Assessments',
        value: props.summary.assessments ?? 0,
        hint: 'Recorded this school year',
        accent: 'text-slate-900',
        href: route('assessments.index'),
    },
    {
        label: 'Awaiting Marking',
        value: awaitingCount.value,
        hint: awaitingCount.value > 0 ? 'Submitted exercises' : 'Nothing pending',
        accent: awaitingCount.value > 0 ? 'text-amber-600' : 'text-slate-900',
        href: '#awaiting-marking',
    },
]);

const exerciseProgress = computed(() => [
    { label: 'Assigned', value: props.remediation.assigned ?? 0, classes: 'bg-slate-100 text-slate-700' },
    { label: 'Submitted', value: props.remediation.submitted ?? 0, classes: 'bg-sky-100 text-sky-700' },
    { label: 'Completed', value: props.remediation.completed ?? 0, classes: 'bg-emerald-100 text-emerald-700' },
]);

const formatDate = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};

const assessmentHref = (assessment) =>
    (assessment.failing_count > 0
        ? route('assessments.remediation', assessment.id)
        : route('assessments.show', assessment.id));
</script>

<template>
    <Head title="Dashboard" />

    <MainAuthLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Admin Overview</p>
                        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Dashboard</h1>
                        <p class="mt-1 text-sm text-slate-500">
                            <span>School year {{ summary.school_year ?? '—' }}</span>
                            <span> · Passing threshold {{ threshold }}%</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="route('assessments.create')"
                            class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                        >
                            New assessment
                        </Link>
                        <Link
                            :href="route('tracker.index')"
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                        >
                            Tracker
                        </Link>
                        <Link
                            :href="route('settings.edit')"
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                        >
                            Settings
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Attention banner -->
            <div
                v-if="awaitingCount > 0"
                class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
            >
                <p class="font-semibold">
                    {{ awaitingCount }} exercise{{ awaitingCount === 1 ? '' : 's' }} submitted and awaiting marking.
                </p>
                <p class="mt-1">
                    Open each session to review the learner's answers, adjust scores, and mark it completed.
                </p>
            </div>

            <!-- Stat cards -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in statCards"
                    :key="card.label"
                    :href="card.href"
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm transition hover:border-slate-300 hover:shadow"
                >
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">{{ card.label }}</p>
                    <p class="mt-2 text-3xl font-semibold" :class="card.accent">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ card.hint }}</p>
                </Link>
            </div>

            <!-- Awaiting marking + recent assessments -->
            <div class="grid gap-4 xl:grid-cols-5">
                <div id="awaiting-marking" class="rounded-3xl border border-slate-100 bg-white shadow-sm xl:col-span-3">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Awaiting marking</p>
                            <p class="text-xs text-slate-400">Submitted exercises, newest first.</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                            {{ pending.length }}
                        </span>
                    </div>

                    <div v-if="pending.length" class="divide-y divide-slate-100">
                        <div
                            v-for="session in pending"
                            :key="session.id"
                            class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ session.learner?.name ?? 'Learner' }}
                                </p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-1 truncate text-xs text-slate-500">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                        :class="kindClasses(session.kind)"
                                    >
                                        {{ kindLabel(session.kind) }}
                                    </span>
                                    <span>{{ session.assessment_title || session.type || 'Exercise' }}</span>
                                    <span v-if="session.section"> · {{ session.section }}</span>
                                    <span v-if="session.quarter"> · Q{{ session.quarter }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="text-xs text-slate-500">
                                    Answered {{ session.answered_count }} / {{ session.total_questions }}
                                </span>
                                <span class="text-xs text-slate-400">{{ formatDateTime(session.submitted_at) }}</span>
                                <Link
                                    :href="route('exercise-sessions.show', session.id)"
                                    class="rounded-full bg-slate-900 px-4 py-2 text-[10px] font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                                >
                                    Review
                                </Link>
                            </div>
                        </div>
                    </div>
                    <p v-else class="px-6 py-10 text-center text-sm text-slate-500">
                        Nothing is waiting to be marked.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-100 bg-white shadow-sm xl:col-span-2">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Recent assessments</p>
                        <p class="text-xs text-slate-400">Latest recorded, with learners below {{ threshold }}%.</p>
                    </div>

                    <div v-if="recentAssessments.length" class="divide-y divide-slate-100">
                        <Link
                            v-for="assessment in recentAssessments"
                            :key="assessment.id"
                            :href="assessmentHref(assessment)"
                            class="flex items-center justify-between gap-3 px-6 py-4 transition hover:bg-slate-50"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ assessment.title || assessment.type || 'Assessment' }}
                                </p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">
                                    <span v-if="assessment.type">{{ assessment.type }}</span>
                                    <span v-if="assessment.section"> · {{ assessment.section }}</span>
                                    <span v-if="assessment.quarter"> · Q{{ assessment.quarter }}</span>
                                    <span> · {{ formatDate(assessment.assessment_date) }}</span>
                                </p>
                            </div>
                            <span
                                class="flex-none rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider"
                                :class="assessment.failing_count > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ assessment.failing_count > 0 ? `${assessment.failing_count} below` : 'On track' }}
                            </span>
                        </Link>
                    </div>
                    <p v-else class="px-6 py-10 text-center text-sm text-slate-500">
                        No assessments recorded yet.
                    </p>
                </div>
            </div>

            <!-- Bottom row -->
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Question bank</p>
                    <div class="mt-4 space-y-3">
                        <Link :href="route('topics.index')" class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm transition hover:bg-slate-100">
                            <span class="font-medium text-slate-700">Topics</span>
                            <span class="font-semibold text-slate-900">{{ counts.topics }}</span>
                        </Link>
                        <Link :href="route('questionnaires.index')" class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm transition hover:bg-slate-100">
                            <span class="font-medium text-slate-700">Questionnaires</span>
                            <span class="font-semibold text-slate-900">{{ counts.questionnaires }}</span>
                        </Link>
                        <Link :href="route('questions.index')" class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm transition hover:bg-slate-100">
                            <span class="font-medium text-slate-700">Questions</span>
                            <span class="font-semibold text-slate-900">{{ counts.questions }}</span>
                        </Link>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Exercises</p>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="row in exerciseProgress"
                            :key="row.label"
                            class="flex items-center justify-between rounded-2xl px-4 py-3 text-sm"
                            :class="row.classes"
                        >
                            <span class="font-medium">{{ row.label }}</span>
                            <span class="font-semibold">{{ row.value }}</span>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-400">
                        Counts cover the current school year.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Recent logins</p>
                        <Link :href="route('tracker.index')" class="text-[10px] font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700">
                            View all
                        </Link>
                    </div>
                    <div v-if="recentLogins.length" class="mt-4 space-y-3">
                        <div
                            v-for="login in recentLogins"
                            :key="login.id"
                            class="rounded-2xl bg-slate-50 px-4 py-3"
                        >
                            <p class="truncate text-sm font-medium text-slate-800">
                                {{ login.name || 'Unknown user' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <span v-if="login.role" class="uppercase tracking-wider">{{ login.role }}</span>
                                <span v-if="login.actor_name"> · via {{ login.actor_name }}</span>
                                <span> · {{ formatDateTime(login.created_at) }}</span>
                            </p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">No login activity recorded.</p>
                </div>
            </div>
        </div>
    </MainAuthLayout>
</template>
