<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MathText from '@/Components/Exercises/MathText.vue';
import InputError from '@/Components/InputError.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    sessionQuestions: {
        type: Array,
        default: () => [],
    },
    questionnaires: {
        type: Array,
        default: () => [],
    },
    availableQuestionnaires: {
        type: Array,
        default: () => [],
    },
});

const scoreValues = ref(
    Object.fromEntries(props.sessionQuestions.map((question) => [question.id, question.score ?? '']))
);

const selectedQuestionnaireIds = ref(props.questionnaires.map((questionnaire) => questionnaire.id));

const form = useForm({
    questionnaire_ids: [],
    scores: [],
    remark: props.session.remark ?? '',
    status: props.session.status ?? 'assigned',
});

const learner = computed(() => props.session.learner ?? {});
const learnerName = computed(() =>
    [learner.value.last_name, learner.value.first_name, learner.value.middle_name ? `${learner.value.middle_name.charAt(0)}.` : '']
        .filter(Boolean)
        .join(', ')
);

const title = computed(
    () => props.session.assessment?.title || props.session.assessment?.assessment_type?.name || 'Exercise session'
);

const attemptedCount = computed(() =>
    props.sessionQuestions.filter((question) => {
        const value = scoreValues.value[question.id];
        return value !== '' && value !== null && value !== undefined;
    }).length
);

const totalScore = computed(() =>
    props.sessionQuestions.reduce((sum, question) => {
        const value = scoreValues.value[question.id];

        if (value === '' || value === null || value === undefined) {
            return sum;
        }

        return sum + Number(value);
    }, 0)
);

const maxScore = computed(() =>
    props.sessionQuestions.reduce((sum, question) => {
        const value = scoreValues.value[question.id];

        if (value === '' || value === null || value === undefined) {
            return sum;
        }

        return sum + Number(question.points);
    }, 0)
);

const percent = computed(() => (maxScore.value > 0 ? ((totalScore.value / maxScore.value) * 100).toFixed(2) : null));

const correctOption = (question) => (question.options ?? []).find((option) => option.is_correct) ?? null;

const addableQuestionnaires = computed(() =>
    props.availableQuestionnaires.filter((questionnaire) => !selectedQuestionnaireIds.value.includes(questionnaire.id))
);

const addQuestionnaire = (event) => {
    const id = Number(event.target.value);

    if (id && !selectedQuestionnaireIds.value.includes(id)) {
        selectedQuestionnaireIds.value = [...selectedQuestionnaireIds.value, id];
    }

    event.target.value = '';
};

const removeQuestionnaire = (questionnaire) => {
    if (!window.confirm(`Remove "${questionnaire.title}" and its captured scores from this session?`)) {
        return;
    }

    selectedQuestionnaireIds.value = selectedQuestionnaireIds.value.filter((id) => id !== questionnaire.id);
};

const submit = () => {
    form.questionnaire_ids = [...selectedQuestionnaireIds.value];
    form.scores = props.sessionQuestions.map((question) => ({
        session_question_id: question.id,
        score: scoreValues.value[question.id] === '' ? null : scoreValues.value[question.id],
    }));

    form.put(route('exercise-sessions.update', props.session.id), { preserveScroll: true });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise session</p>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ learnerName }}</h1>
                    <p class="text-sm text-slate-500">
                        {{ title }}
                        <span
                            class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                            :class="session.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ session.status }}
                        </span>
                    </p>
                </div>
                <Link
                    :href="route('assessments.remediation', session.assessment_id)"
                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                >
                    Back to preventive exercises
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Attempted</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ attemptedCount }} / {{ sessionQuestions.length }}</p>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Score (attempted)</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ totalScore }} / {{ maxScore }}</p>
                </div>
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Percent</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ percent !== null ? `${percent}%` : '—' }}</p>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Questionnaires</p>
                        <p class="text-sm text-slate-500">Remove one to drop its frozen questions and their scores.</p>
                    </div>
                    <select
                        v-if="addableQuestionnaires.length"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none"
                        @change="addQuestionnaire"
                    >
                        <option value="">Add questionnaire…</option>
                        <option v-for="questionnaire in addableQuestionnaires" :key="questionnaire.id" :value="questionnaire.id">
                            {{ questionnaire.topic?.name ? `${questionnaire.topic.name} · ` : '' }}{{ questionnaire.title }}
                        </option>
                    </select>
                </div>

                <div v-if="questionnaires.length" class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="questionnaire in questionnaires"
                        :key="questionnaire.id"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                        :class="{ 'border-rose-200 text-rose-600': !selectedQuestionnaireIds.includes(questionnaire.id) }"
                    >
                        {{ questionnaire.title }}
                        <button
                            type="button"
                            class="text-rose-500 hover:text-rose-700"
                            @click="removeQuestionnaire(questionnaire)"
                        >
                            ×
                        </button>
                    </span>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">No questionnaires frozen into this session.</p>
            </div>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div class="px-6 py-5">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Frozen questions</p>
                    <p class="text-sm text-slate-500">Blank score means not attempted. A student resubmission recomputes MCQ auto-scores, so mark the session Completed once marking is final.</p>
                </div>
                <div v-if="!sessionQuestions.length" class="px-6 pb-8 text-sm text-slate-500">
                    No questions in this session.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Question</th>
                                <th class="px-6 py-3 font-semibold text-center">Points</th>
                                <th class="px-6 py-3 font-semibold text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="question in sessionQuestions"
                                :key="question.id"
                                class="border-b last:border-b-0 odd:bg-white even:bg-slate-50 align-top"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <img
                                            v-if="question.image_url"
                                            :src="question.image_url"
                                            class="h-16 w-16 rounded-xl object-cover"
                                            alt="Question image"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                                                    :class="question.type === 'multiple_choice' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'"
                                                >
                                                    {{ question.type === 'multiple_choice' ? 'MCQ' : 'Text' }}
                                                </span>
                                                <MathText
                                                    v-if="question.prompt_text"
                                                    :content="question.prompt_text"
                                                    class="font-semibold text-slate-900"
                                                />
                                                <span v-else class="font-semibold text-slate-900">(image only)</span>
                                            </div>

                                            <ul v-if="question.type === 'multiple_choice'" class="mt-2 space-y-1">
                                                <li
                                                    v-for="(option, index) in question.options"
                                                    :key="option.id"
                                                    class="flex items-center gap-2 rounded-xl px-2 py-1 text-sm"
                                                    :class="[
                                                        option.is_correct ? 'font-semibold text-emerald-700' : 'text-slate-600',
                                                        question.selected_option_id === option.id ? 'bg-amber-50 ring-1 ring-amber-200' : '',
                                                    ]"
                                                >
                                                    <span class="text-xs uppercase tracking-widest text-slate-400">{{ String.fromCharCode(65 + index) }}</span>
                                                    <MathText :content="option.label" />
                                                    <span v-if="option.is_correct" class="text-[10px] uppercase tracking-widest text-emerald-600">Correct</span>
                                                    <span v-if="question.selected_option_id === option.id" class="text-[10px] uppercase tracking-widest text-amber-600">Student answer</span>
                                                </li>
                                            </ul>

                                            <p v-if="question.type === 'multiple_choice' && !question.selected_option_id" class="mt-2 text-xs text-slate-500">
                                                No answer selected.
                                            </p>

                                            <p v-if="question.type === 'multiple_choice'" class="mt-2 text-xs text-slate-500">
                                                Answer key:
                                                <MathText :content="correctOption(question)?.label ?? '—'" />
                                            </p>
                                            <p v-else-if="question.answer_key" class="mt-2 text-xs text-slate-500">
                                                Answer key: <MathText :content="question.answer_key" />
                                            </p>

                                            <div v-if="question.type !== 'multiple_choice'" class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                                <span class="font-semibold uppercase tracking-widest text-slate-400">Student answer:</span>
                                                <MathText v-if="question.response_text" :content="question.response_text" class="ml-2" />
                                                <span v-else class="ml-2 text-slate-400">No answer</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center text-slate-700">{{ question.points }}</td>
                                <td class="px-6 py-4 text-right">
                                    <input
                                        v-model="scoreValues[question.id]"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="—"
                                        class="w-24 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Remark</span>
                        <textarea
                            v-model="form.remark"
                            rows="3"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        ></textarea>
                        <InputError :message="form.errors.remark" class="mt-1" />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Status</span>
                        <select
                            v-model="form.status"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        >
                            <option value="assigned">Assigned</option>
                            <option value="submitted">Submitted</option>
                            <option value="completed">Completed</option>
                        </select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </label>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:opacity-40"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : 'Save session' }}
                    </button>
                </div>
                <InputError :message="form.errors.scores" class="mt-2" />
            </div>
        </div>
    </MainAuthLayout>
</template>
