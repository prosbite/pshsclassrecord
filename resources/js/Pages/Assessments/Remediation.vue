<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    assessment: {
        type: Object,
        required: true,
    },
    poolTopics: {
        type: Array,
        default: () => [],
    },
    poolQuestionnaires: {
        type: Array,
        default: () => [],
    },
    bankTopics: {
        type: Array,
        default: () => [],
    },
    bankQuestionnaires: {
        type: Array,
        default: () => [],
    },
    failingLearners: {
        type: Array,
        default: () => [],
    },
    threshold: {
        type: Number,
        default: 75,
    },
    sessions: {
        type: Array,
        default: () => [],
    },
});

const selectedLearnerIds = ref(
    props.failingLearners.filter((row) => row.is_failing && row.learner).map((row) => row.learner.id)
);

const selectedQuestionnaireIds = ref(props.poolQuestionnaires.map((questionnaire) => questionnaire.id));

const form = useForm({
    learner_ids: [],
    questionnaire_ids: [],
});

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

    form.post(route('assessments.remediation.sessions.store', props.assessment.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedLearnerIds.value = [];
        },
    });
};

const confirmDeleteSession = (session) => {
    if (!window.confirm('Delete this exercise session and its captured scores?')) {
        return;
    }

    router.delete(route('exercise-sessions.destroy', session.id), { preserveScroll: true });
};

const title = computed(() => props.assessment.title || props.assessment.assessment_type?.name || 'Assessment');
const selectedCount = computed(() => selectedLearnerIds.value.length);
const canSubmit = computed(() => selectedCount.value > 0 && selectedQuestionnaireIds.value.length > 0);
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Preventive Exercises</p>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
                    <p class="text-sm text-slate-500">
                        {{ assessment.section?.section_name || 'Section' }} · Passing threshold {{ threshold }}%
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('assessments.edit', assessment.id)"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Edit pool
                    </Link>
                    <Link
                        :href="route('assessments.show', assessment.id)"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Back to assessment
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Pool topics</p>
                    <div v-if="poolTopics.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="topic in poolTopics"
                            :key="topic.id"
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                        >
                            {{ topic.name }}
                        </span>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-500">No topics attached.</p>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Pool questionnaires</p>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ poolQuestionnaires.length }} questionnaire{{ poolQuestionnaires.length === 1 ? '' : 's' }} selected.
                    </p>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Learners</p>
                <p class="text-sm text-slate-500">
                    Failing learners are pre-selected. Tentative scores are marked but never auto-selected.
                </p>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Assign</th>
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

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div class="px-6 py-5">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Sessions</p>
                    <p class="text-sm text-slate-500">{{ sessions.length }} assigned</p>
                </div>
                <div v-if="!sessions.length" class="px-6 pb-8 text-sm text-slate-500">
                    No sessions yet.
                </div>
                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Learner</th>
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
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ learnerName(session.learner) }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ (session.questionnaires || []).length }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">
                                {{ session.answered_count ?? 0 }} / {{ (session.session_questions || []).length }}
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
                                        Score
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="confirmDeleteSession(session)"
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
