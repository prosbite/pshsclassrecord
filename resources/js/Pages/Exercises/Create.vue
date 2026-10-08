<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
    assessment: {
        type: Object,
        default: null,
    },
    failingLearners: {
        type: Array,
        default: () => [],
    },
    threshold: {
        type: Number,
        default: 75,
    },
    poolQuestionnaires: {
        type: Array,
        default: () => [],
    },
    bankQuestionnaires: {
        type: Array,
        default: () => [],
    },
});

const activeSection = computed(() =>
    props.sections.find((section) => section.id === props.selectedSectionId) ?? null
);

const assessments = computed(() => activeSection.value?.assessments ?? []);

const assessmentLabel = (assessment) => assessment.title || assessment.type || 'Assessment';

const selectSection = (event) => {
    router.get(route('exercises.create'), {
        section: event.target.value ? Number(event.target.value) : null,
    });
};

const selectAssessment = (event) => {
    router.get(route('exercises.create'), {
        section: props.selectedSectionId,
        assessment: event.target.value ? Number(event.target.value) : null,
    });
};

const defaultLearnerSelection = (kind) => props.failingLearners
    .filter((row) => row.learner && (kind === 'preventive' ? row.is_failing : !row.is_failing))
    .map((row) => row.learner.id);

const selectedLearnerIds = ref(defaultLearnerSelection('preventive'));

const selectedQuestionnaireIds = ref(props.poolQuestionnaires.map((questionnaire) => questionnaire.id));

const form = useForm({
    learner_ids: [],
    questionnaire_ids: [],
    kind: 'preventive',
});

watch(
    () => form.kind,
    (kind) => {
        selectedLearnerIds.value = defaultLearnerSelection(kind);
    },
);

const learnerName = (learner) =>
    [learner?.last_name, learner?.first_name, learner?.middle_name ? `${learner.middle_name.charAt(0)}.` : '']
        .filter(Boolean)
        .join(', ');

const toggleLearner = (learnerId) => {
    if (selectedLearnerIds.value.includes(learnerId)) {
        selectedLearnerIds.value = selectedLearnerIds.value.filter((id) => id !== learnerId);
    } else {
        selectedLearnerIds.value = [...selectedLearnerIds.value, learnerId];
    }
};

const selectableLearnerIds = computed(() =>
    props.failingLearners
        .map((row) => row.learner?.id)
        .filter((id) => id !== null && id !== undefined)
);

const allLearnersSelected = computed(() =>
    selectableLearnerIds.value.length > 0
    && selectableLearnerIds.value.every((id) => selectedLearnerIds.value.includes(id))
);

const someLearnersSelected = computed(() =>
    selectedLearnerIds.value.length > 0 && !allLearnersSelected.value
);

const toggleAllLearners = () => {
    selectedLearnerIds.value = allLearnersSelected.value ? [] : [...selectableLearnerIds.value];
};

const toggleQuestionnaire = (questionnaireId) => {
    if (selectedQuestionnaireIds.value.includes(questionnaireId)) {
        selectedQuestionnaireIds.value = selectedQuestionnaireIds.value.filter((id) => id !== questionnaireId);
    } else {
        selectedQuestionnaireIds.value = [...selectedQuestionnaireIds.value, questionnaireId];
    }
};

const submitSessions = () => {
    form.learner_ids = [...selectedLearnerIds.value];
    form.questionnaire_ids = [...selectedQuestionnaireIds.value];

    form.post(route('exercises.sessions.store', props.assessment.id), {
        preserveScroll: true,
    });
};

const selectedCount = computed(() => selectedLearnerIds.value.length);
const canSubmit = computed(() => selectedCount.value > 0 && selectedQuestionnaireIds.value.length > 0);
const title = computed(() => props.assessment?.title || props.assessment?.type || 'Assessment');
</script>

<template>
    <Head title="Create Exercise" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercises</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Create Exercise</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Pick a section and assessment, choose the learners and questionnaires, then create the sessions.
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

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Section</span>
                        <select
                            :value="selectedSectionId ?? ''"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                            @change="selectSection"
                        >
                            <option value="">Select a section</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id">
                                {{ section.section_name }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Assessment</span>
                        <select
                            :value="selectedAssessmentId ?? ''"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                            @change="selectAssessment"
                        >
                            <option value="">Select an assessment</option>
                            <option v-for="item in assessments" :key="item.id" :value="item.id">
                                {{ assessmentLabel(item) }}{{ item.quarter ? ` · Q${item.quarter}` : '' }}
                            </option>
                        </select>
                    </label>
                </div>
            </div>

            <div v-if="!assessment" class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
                Select a section and assessment to assign learners and questionnaires.
            </div>

            <template v-else>
                <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Learners</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ title }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ assessment.section || 'Section' }} · Passing threshold {{ threshold }}%
                    </p>
                    <p class="mt-3 text-sm text-slate-500">
                        <template v-if="form.kind === 'preventive'">
                            Failing learners are pre-selected. Tentative scores are marked but never auto-selected.
                        </template>
                        <template v-else>
                            Passing learners are pre-selected. Tentative scores are marked but never auto-selected.
                        </template>
                        Changing the exercise type resets this selection.
                        <span class="font-semibold text-slate-700">
                            {{ selectedCount }} of {{ selectableLearnerIds.length }} selected.
                        </span>
                    </p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        <label class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                                :checked="allLearnersSelected"
                                                :indeterminate="someLearnersSelected"
                                                :disabled="!selectableLearnerIds.length"
                                                @change="toggleAllLearners"
                                            />
                                            Assign
                                        </label>
                                    </th>
                                    <th class="px-4 py-3 font-semibold">Learner</th>
                                    <th class="px-4 py-3 font-semibold text-right">Score</th>
                                    <th class="px-4 py-3 font-semibold text-right">Percent</th>
                                    <th class="px-4 py-3 font-semibold text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in failingLearners"
                                    :key="row.learner?.id"
                                    class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                                >
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                            :checked="selectedLearnerIds.includes(row.learner?.id)"
                                            @change="toggleLearner(row.learner?.id)"
                                        />
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        {{ learnerName(row.learner) }}
                                        <span
                                            v-if="row.tentative"
                                            class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700"
                                        >
                                            Tentative
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        {{ row.score ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        {{ row.percent !== null ? `${row.percent}%` : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                            :class="row.is_failing
                                                ? 'bg-rose-100 text-rose-700'
                                                : 'bg-slate-100 text-slate-600'"
                                        >
                                            {{ row.is_failing ? 'Failing' : 'Passing' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!failingLearners.length">
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                        No active learners in this section for the current school year.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Disseminate questionnaires</p>
                    <p class="text-sm text-slate-500">
                        Each selected learner gets one session with the chosen questionnaires. Existing sessions are skipped.
                    </p>

                    <div class="mt-5">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Exercise type</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label
                                v-for="option in [
                                    { value: 'preventive', label: 'Preventive' },
                                    { value: 'enhancement', label: 'Enhancement' },
                                ]"
                                :key="option.value"
                                class="flex cursor-pointer items-center gap-2 rounded-2xl border px-4 py-2 text-sm transition"
                                :class="form.kind === option.value
                                    ? 'border-slate-400 bg-slate-100 font-semibold text-slate-900'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                            >
                                <input
                                    v-model="form.kind"
                                    type="radio"
                                    class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-slate-500"
                                    :value="option.value"
                                />
                                {{ option.label }}
                            </label>
                        </div>
                    </div>

                    <div v-if="bankQuestionnaires.length" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="questionnaire in bankQuestionnaires"
                            :key="questionnaire.id"
                            class="flex items-center gap-3 rounded-2xl border border-slate-100 px-4 py-2 text-sm"
                            :class="selectedQuestionnaireIds.includes(questionnaire.id) ? 'bg-slate-50' : 'bg-white'"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                :checked="selectedQuestionnaireIds.includes(questionnaire.id)"
                                @change="toggleQuestionnaire(questionnaire.id)"
                            />
                            <span class="text-slate-700">
                                {{ questionnaire.topic?.name ? `${questionnaire.topic.name} · ` : '' }}{{ questionnaire.title }}
                            </span>
                        </label>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">No questionnaires exist in the bank yet.</p>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-500">
                            {{ selectedCount }} learner{{ selectedCount === 1 ? '' : 's' }} ·
                            {{ selectedQuestionnaireIds.length }} questionnaire{{ selectedQuestionnaireIds.length === 1 ? '' : 's' }}
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:opacity-40"
                            :disabled="!canSubmit || form.processing"
                            @click="submitSessions"
                        >
                            {{ form.processing ? 'Creating…' : 'Create sessions' }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.questionnaire_ids"
                        class="mt-2 text-sm font-semibold text-rose-600"
                    >
                        {{ form.errors.questionnaire_ids }}
                    </p>
                </div>
            </template>
        </div>
    </MainAuthLayout>
</template>
