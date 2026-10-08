<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

defineProps({
    learner: {
        type: Object,
        required: true,
    },
    sessions: {
        type: Array,
        default: () => [],
    },
});

const assessmentLabel = (assessment) => assessment.title || assessment.type || 'Assessment';

const kindLabel = (kind) => (kind === 'enhancement' ? 'Enhancement' : 'Preventive');

const kindClasses = (kind) => ({
    preventive: 'bg-indigo-100 text-indigo-700',
    enhancement: 'bg-emerald-100 text-emerald-700',
}[kind] ?? 'bg-slate-100 text-slate-600');

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`Exercises · ${learner.name}`" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise history</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{{ learner.name }}</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            {{ learner.email || 'No email on file' }} · {{ sessions.length }} session{{ sessions.length === 1 ? '' : 's' }} across all assessments.
                        </p>
                    </div>
                    <Link
                        :href="route('exercises.index')"
                        class="inline-flex items-center rounded-full border border-white/20 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-white/10"
                    >
                        Back to list
                    </Link>
                </div>
            </section>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div v-if="!sessions.length" class="px-6 py-10 text-center text-sm text-slate-500">
                    This learner has no exercise sessions yet.
                </div>

                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Assessment</th>
                            <th class="px-6 py-3 font-semibold text-center">Section</th>
                            <th class="px-6 py-3 font-semibold text-center">Quarter</th>
                            <th class="px-6 py-3 font-semibold text-center">Kind</th>
                            <th class="px-6 py-3 font-semibold text-center">Answered</th>
                            <th class="px-6 py-3 font-semibold text-center">Percent</th>
                            <th class="px-6 py-3 font-semibold text-center">Status</th>
                            <th class="px-6 py-3 font-semibold text-center">Completed</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="session in sessions"
                            :key="session.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 font-semibold text-slate-900">
                                {{ assessmentLabel(session.assessment) }}
                                <span v-if="session.assessment.type" class="block text-xs font-normal text-slate-500">
                                    {{ session.assessment.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ session.assessment.section || '—' }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">
                                {{ session.assessment.quarter ? `Q${session.assessment.quarter}` : '—' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                    :class="kindClasses(session.kind)"
                                >
                                    {{ kindLabel(session.kind) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700">
                                {{ session.answered_count }} / {{ session.total_questions }}
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700">
                                {{ session.percent !== null && session.percent !== undefined ? `${session.percent}%` : '—' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700': session.status === 'completed',
                                        'bg-sky-100 text-sky-700': session.status === 'submitted',
                                        'bg-slate-100 text-slate-600': session.status === 'assigned',
                                    }"
                                >
                                    {{ session.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ formatDate(session.completed_at) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="route('exercise-sessions.show', session.id)"
                                    class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </MainAuthLayout>
</template>
