<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatDate } from '@/Composables/utilities.js';

const props = defineProps({
    assessment: {
        type: Object,
        required: true,
    },
    learners: {
        type: Array,
        default: () => [],
    },
});

const confirmDelete = () => {
    if (!window.confirm('Delete this assessment? This cannot be undone.')) {
        return;
    }

    router.delete(route('assessments.destroy', props.assessment.id));
};

const averageScore = computed(() => {
    if (!props.learners.length) {
        return null;
    }

    const scores = props.learners
        .map((learner) => Number(learner.score))
        .filter((score) => !Number.isNaN(score));

    if (!scores.length) {
        return null;
    }

    return (scores.reduce((sum, score) => sum + score, 0) / scores.length).toFixed(2);
});

const gradeLabel = computed(() => props.assessment.section?.grade_level?.grade_level ?? 'Unassigned grade');
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <Link
                :href="route('assessments.edit', assessment.id)"
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
            >
                Edit assessment
            </Link>
            <button
                type="button"
                class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
                @click="confirmDelete"
            >
                Delete
            </button>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Assessment</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">
                    {{ assessment.title || assessment.assessment_type?.name || 'Assessment' }}
                </p>
                <p class="text-sm text-slate-500">
                    {{ assessment.section?.section_name || 'General section' }} · {{ gradeLabel }}
                </p>
            </div>
            <div class="rounded-3xl bg-gradient-to-br from-sky-500 to-blue-600 p-6 text-white shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em]">Details</p>
                <p class="mt-2 text-2xl font-semibold">{{ assessment.assessment_type?.percentage ?? '—' }}%</p>
                <p class="text-sm text-white/80">
                    {{ formatDate(assessment.assessment_date) }} · {{ formatDate(assessment.created_at) }}
                </p>
                <p class="text-xs mt-4 text-white/70">
                    Quarter {{ assessment.quarter?.quarter ?? '—' }} · {{ assessment.school_year?.year_start ?? '—' }}-{{ assessment.school_year?.year_end ?? '—' }}
                </p>
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Teacher</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">
                    {{ assessment.user?.name || 'Unassigned' }}
                </p>
                <p class="text-xs text-slate-500">
                    Learners: {{ learners.length }}
                </p>
                <p v-if="averageScore" class="text-xs text-slate-500">
                    Average score: {{ averageScore }}
                </p>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
            <p class="text-xs uppercase tracking-[0.4em] text-slate-400">More information</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Assessment type</p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ assessment.assessment_type?.name || '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Assessment date</p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ formatDate(assessment.assessment_date) || '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Quarter</p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ assessment.quarter?.quarter ? `Quarter ${assessment.quarter.quarter}` : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">School year</p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ assessment.school_year ? `${assessment.school_year.year_start}-${assessment.school_year.year_end}` : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Section</p>
                    <p class="text-sm font-semibold text-slate-900">
                        {{ assessment.section?.section_name || '—' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
            <div class="px-6 py-5 sm:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Learner scores</p>
                        <p class="text-sm text-slate-500">
                            {{ learners.length }} enrolled {{ learners.length === 1 ? 'learner' : 'learners' }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Learner</th>
                            <th class="px-6 py-3 font-semibold">Email</th>
                            <th class="px-6 py-3 font-semibold text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="learner in learners"
                            :key="learner.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                                {{ [learner.first_name, learner.middle_name, learner.last_name].filter(Boolean).join(' ') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ learner.email || '—' }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">
                                <span :class="learner.tentative ? 'text-amber-600' : ''">
                                    {{ learner.score ?? '—' }}
                                </span>
                                <span
                                    v-if="learner.tentative"
                                    class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700"
                                >
                                    Tentative
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
