<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    selectedSectionId: {
        type: [Number, String],
        default: null,
    },
    selectedAssessmentId: {
        type: [Number, String],
        default: null,
    },
    kind: {
        type: String,
        default: 'preventive',
    },
    sessions: {
        type: Array,
        default: () => [],
    },
});

const activeSection = computed(() =>
    props.sections.find((section) => section.id === props.selectedSectionId) ?? null
);

const assessments = computed(() => activeSection.value?.assessments ?? []);

const assessmentLabel = (assessment) => assessment.title || assessment.type || 'Assessment';

const kindLabel = (kind) => (kind === 'enhancement' ? 'Enhancement' : 'Preventive');

const kindClasses = (kind) => ({
    preventive: 'bg-indigo-100 text-indigo-700',
    enhancement: 'bg-emerald-100 text-emerald-700',
}[kind] ?? 'bg-slate-100 text-slate-600');

const selectSection = (section) => {
    if (section.id === props.selectedSectionId) {
        return;
    }

    router.get(route('exercises.index'), {
        section: section.id,
        assessment: section.assessments[0]?.id ?? null,
        kind: props.kind,
    }, {
        preserveState: true,
        replace: true,
    });
};

const selectAssessment = (event) => {
    router.get(route('exercises.index'), {
        section: props.selectedSectionId,
        assessment: event.target.value ? Number(event.target.value) : null,
        kind: props.kind,
    }, {
        preserveState: true,
        replace: true,
    });
};

const selectKind = (kind) => {
    if (kind === props.kind) {
        return;
    }

    router.get(route('exercises.index'), {
        section: props.selectedSectionId,
        assessment: props.selectedAssessmentId,
        kind,
    }, {
        preserveState: true,
        replace: true,
    });
};

const createExercise = () => {
    router.get(route('exercises.create'), { section: props.selectedSectionId });
};

const deleteSession = (session) => {
    if (!window.confirm('Delete this exercise session and its captured scores?')) {
        return;
    }

    router.delete(route('exercises.sessions.destroy', session.id), {
        data: { kind: props.kind },
        preserveScroll: true,
    });
};

const deleteAllSessions = () => {
    if (!props.sessions.length) {
        return;
    }

    if (!window.confirm(`Delete all ${props.sessions.length} exercise session(s) for this assessment? This cannot be undone.`)) {
        return;
    }

    router.delete(route('exercises.sessions.destroy-all', props.selectedAssessmentId), {
        data: { kind: props.kind },
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Exercises" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercises</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Exercise Sessions</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Browse and manage exercise sessions by section and assessment, then open a session to score it.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full bg-white px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-900 shadow transition hover:bg-slate-100"
                        @click="createExercise"
                    >
                        Create Exercise
                    </button>
                </div>
            </section>

            <div v-if="!sections.length" class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                No sections have assessments for the current school year.
            </div>

            <template v-else>
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            type="button"
                            class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                            :class="section.id === selectedSectionId
                                ? 'bg-slate-900 text-white shadow'
                                : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                            @click="selectSection(section)"
                        >
                            {{ section.section_name }}
                        </button>
                    </div>

                    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <label class="block w-full sm:max-w-md">
                            <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Assessment</span>
                            <select
                                :value="selectedAssessmentId ?? ''"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                @change="selectAssessment"
                            >
                                <option v-if="!assessments.length" value="">No assessments</option>
                                <option v-for="assessment in assessments" :key="assessment.id" :value="assessment.id">
                                    {{ assessmentLabel(assessment) }}{{ assessment.quarter ? ` · Q${assessment.quarter}` : '' }}
                                </option>
                            </select>
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in [
                                    { value: 'preventive', label: 'Preventive' },
                                    { value: 'enhancement', label: 'Enhancement' },
                                ]"
                                :key="option.value"
                                type="button"
                                class="rounded-2xl border px-4 py-2 text-sm transition"
                                :class="kind === option.value
                                    ? 'border-slate-400 bg-slate-100 font-semibold text-slate-900'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                                @click="selectKind(option.value)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-5">
                        <div>
                            <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Sessions</p>
                            <p class="text-sm text-slate-500">{{ sessions.length }} {{ kindLabel(kind).toLowerCase() }} session{{ sessions.length === 1 ? '' : 's' }}</p>
                        </div>
                        <button
                            v-if="sessions.length"
                            type="button"
                            class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-rose-600 transition hover:border-rose-300 hover:bg-rose-100"
                            @click="deleteAllSessions"
                        >
                            Delete all
                        </button>
                    </div>

                    <div v-if="!sessions.length" class="px-6 pb-8 text-sm text-slate-500">
                        No sessions of this kind yet.
                    </div>

                    <table v-else class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Learner</th>
                                <th class="px-6 py-3 font-semibold text-center">Kind</th>
                                <th class="px-6 py-3 font-semibold text-center">Questionnaires</th>
                                <th class="px-6 py-3 font-semibold text-center">Answered</th>
                                <th class="px-6 py-3 font-semibold text-center">Attempted</th>
                                <th class="px-6 py-3 font-semibold text-center">Percent</th>
                                <th class="px-6 py-3 font-semibold text-center">Status</th>
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
                                    <Link
                                        v-if="session.learner?.id"
                                        :href="route('exercises.students.show', session.learner.id)"
                                        class="text-slate-900 hover:text-indigo-600"
                                    >
                                        {{ session.learner.name_last_first }}
                                    </Link>
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
                                <td class="px-6 py-4 text-center text-slate-700">{{ session.questionnaires_count }}</td>
                                <td class="px-6 py-4 text-center text-slate-700">
                                    {{ session.answered_count }} / {{ session.total_questions }}
                                </td>
                                <td class="px-6 py-4 text-center text-slate-700">{{ session.attempted_count }}</td>
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
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="route('exercise-sessions.show', session.id)"
                                            class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                        >
                                            Open
                                        </Link>
                                        <button
                                            type="button"
                                            class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                            @click="deleteSession(session)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </MainAuthLayout>
</template>
