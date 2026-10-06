<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    ongoing: {
        type: Array,
        default: () => [],
    },
    completed: {
        type: Array,
        default: () => [],
    },
});

const hasAny = computed(() => props.ongoing.length > 0 || props.completed.length > 0);

const assessmentTitle = (row) => row.assessment?.title || row.assessment?.type || 'Exercise';

const quarterLabel = (row) => (row.assessment?.quarter ? `Quarter ${row.assessment.quarter}` : null);

const kindLabel = (row) => (row.kind === 'enhancement' ? 'Enhancement' : 'Preventive');

const kindClasses = (row) => (row.kind === 'enhancement'
    ? 'bg-emerald-100 text-emerald-700'
    : 'bg-indigo-100 text-indigo-700');

const statusClasses = (status) => ({
    assigned: 'bg-amber-100 text-amber-700',
    submitted: 'bg-sky-100 text-sky-700',
    completed: 'bg-emerald-100 text-emerald-700',
}[status] ?? 'bg-slate-100 text-slate-600');

const statusLabel = (status) => ({
    assigned: 'Assigned',
    submitted: 'Submitted',
    completed: 'Completed',
}[status] ?? status);

const percentLabel = (row) =>
    row.percent !== null && row.percent !== undefined ? `${row.percent}%` : '—';
</script>

<template>
    <StudentLayout>
        <Head title="Exercises" />

        <div class="space-y-8">
            <div class="rounded-3xl border border-slate-100 bg-white p-8 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-3 py-1">
                            <div class="h-2 w-2 rounded-full bg-indigo-500"></div>
                            <p class="text-xs font-medium uppercase tracking-widest text-indigo-600">Exercises</p>
                        </div>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900">Your Exercises</h2>
                        <p class="mt-2 text-sm text-slate-500">
                            Answer the exercises your teacher assigned and track their progress here.
                        </p>
                    </div>
                    <Link
                        :href="route('student.dashboard')"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Back to dashboard
                    </Link>
                </div>
            </div>

            <div v-if="!hasAny" class="rounded-3xl border border-slate-100 bg-white p-16 text-center shadow-sm">
                <p class="text-slate-400">You have no exercises right now.</p>
                <p class="mt-2 text-sm text-slate-500">Check back after your teacher assigns one.</p>
            </div>

            <template v-else>
                <div class="rounded-3xl border border-slate-100 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Ongoing</p>
                    </div>

                    <div v-if="ongoing.length" class="divide-y divide-slate-100">
                        <div
                            v-for="row in ongoing"
                            :key="row.id"
                            class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-slate-900">{{ assessmentTitle(row) }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs uppercase tracking-widest text-slate-400">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wider"
                                        :class="kindClasses(row)"
                                    >
                                        {{ kindLabel(row) }}
                                    </span>
                                    <span v-if="row.assessment?.type">{{ row.assessment.type }}</span>
                                    <span v-if="quarterLabel(row)"> · {{ quarterLabel(row) }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-4">
                                <span class="text-sm text-slate-500">
                                    Answered {{ row.answered_count }} / {{ row.total_questions }}
                                </span>
                                <span
                                    class="rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-wider"
                                    :class="statusClasses(row.status)"
                                >
                                    {{ statusLabel(row.status) }}
                                </span>
                                <Link
                                    :href="route('student.remediation.show', row.id)"
                                    class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                                >
                                    Open
                                </Link>
                            </div>
                        </div>
                    </div>

                    <p v-else class="px-6 py-8 text-sm text-slate-500">Nothing ongoing.</p>
                </div>

                <div class="rounded-3xl border border-slate-100 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <p class="text-sm font-bold uppercase tracking-widest text-slate-500">Completed</p>
                    </div>

                    <div v-if="completed.length" class="divide-y divide-slate-100">
                        <div
                            v-for="row in completed"
                            :key="row.id"
                            class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-slate-900">{{ assessmentTitle(row) }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs uppercase tracking-widest text-slate-400">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wider"
                                        :class="kindClasses(row)"
                                    >
                                        {{ kindLabel(row) }}
                                    </span>
                                    <span v-if="row.assessment?.type">{{ row.assessment.type }}</span>
                                    <span v-if="quarterLabel(row)"> · {{ quarterLabel(row) }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-4">
                                <span class="text-sm text-slate-500">
                                    Answered {{ row.answered_count }} / {{ row.total_questions }}
                                </span>
                                <span class="text-sm font-semibold text-slate-900">{{ percentLabel(row) }}</span>
                                <span
                                    class="rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-wider"
                                    :class="statusClasses(row.status)"
                                >
                                    {{ statusLabel(row.status) }}
                                </span>
                                <Link
                                    :href="route('student.remediation.show', row.id)"
                                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                                >
                                    Review
                                </Link>
                            </div>
                        </div>
                    </div>

                    <p v-else class="px-6 py-8 text-sm text-slate-500">No completed exercises yet.</p>
                </div>
            </template>
        </div>
    </StudentLayout>
</template>
