<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';
import MathText from '@/Components/Exercises/MathText.vue';

const props = defineProps({
    session: {
        type: Object,
        required: true,
    },
    questionnaires: {
        type: Array,
        default: () => [],
    },
    sessionQuestions: {
        type: Array,
        default: () => [],
    },
});

const completed = computed(() => props.session.status === 'completed');
const locked = computed(() => ['submitted', 'completed'].includes(props.session.status));

const answers = reactive({});

props.sessionQuestions.forEach((question) => {
    answers[question.id] = {
        selected_option_id: question.selected_option_id ?? '',
        response_text: question.response_text ?? '',
    };
});

const groups = computed(() => {
    const used = new Set();
    const result = props.questionnaires
        .map((questionnaire) => {
            const questions = props.sessionQuestions.filter(
                (question) => question.questionnaire_id === questionnaire.id,
            );

            questions.forEach((question) => used.add(question.id));

            return {
                key: `q-${questionnaire.id}`,
                title: questionnaire.title,
                topic: questionnaire.topic,
                questions,
            };
        })
        .filter((group) => group.questions.length > 0);

    const leftovers = props.sessionQuestions.filter((question) => !used.has(question.id));

    if (leftovers.length) {
        result.push({ key: 'q-other', title: 'Additional questions', topic: null, questions: leftovers });
    }

    return result;
});

const title = computed(
    () => props.session.assessment?.title || props.session.assessment?.type || 'Preventive exercise',
);

const percentLabel = computed(() =>
    props.session.percent !== null && props.session.percent !== undefined ? `${props.session.percent}%` : '—',
);

const form = useForm({ answers: [] });

const buildAnswers = () =>
    props.sessionQuestions.map((question) => {
        const answer = answers[question.id] ?? {};
        const selected = answer.selected_option_id;

        return {
            session_question_id: question.id,
            selected_option_id: selected === '' || selected === null || selected === undefined
                ? null
                : selected,
            response_text: answer.response_text === '' ? null : (answer.response_text ?? null),
        };
    });

const submit = () => {
    if (!window.confirm('Submit your answers? Once submitted, you can no longer edit them.')) {
        return;
    }

    form.answers = buildAnswers();
    form.put(route('student.remediation.submit', props.session.id), { preserveScroll: true });
};
</script>

<template>
    <StudentLayout>
        <Head :title="title" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Preventive Exercises</p>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ title }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        <span v-if="session.assessment?.type">{{ session.assessment.type }}</span>
                        <span v-if="session.assessment?.quarter"> · Quarter {{ session.assessment.quarter }}</span>
                    </p>
                </div>
                <Link
                    :href="route('student.remediation.index')"
                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                >
                    Back to preventive exercises
                </Link>
            </div>

            <div
                v-if="completed"
                class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6 text-sm text-emerald-800"
            >
                <p class="font-semibold">This exercise is completed and read-only.</p>
                <p class="mt-1">Your teacher has finished marking it. Review your marks below.</p>
            </div>

            <div
                v-else-if="locked"
                class="rounded-3xl border border-sky-200 bg-sky-50 p-6 text-sm text-sky-800"
            >
                <p class="font-semibold">This exercise has been submitted and is now locked.</p>
                <p class="mt-1">Your answers can no longer be edited. Your teacher will mark them and return the results.</p>
            </div>

            <div v-if="completed" class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Score</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">
                        {{ session.total_score ?? '—' }} / {{ session.max_score ?? '—' }}
                    </p>
                </div>
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Percent</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ percentLabel }}</p>
                </div>
                <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Answered</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">
                        {{ session.answered_count }} / {{ session.total_questions }}
                    </p>
                </div>
            </div>

            <div v-if="!sessionQuestions.length" class="rounded-3xl border border-slate-100 bg-white p-10 text-center text-slate-500 shadow-sm">
                This exercise has no questions yet.
            </div>

            <form v-else class="space-y-6" @submit.prevent="submit">
                <div
                    v-for="group in groups"
                    :key="group.key"
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm sm:p-8"
                >
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">
                        <span v-if="group.topic">{{ group.topic }} · </span>{{ group.title }}
                    </p>

                    <div class="mt-6 space-y-8">
                        <div
                            v-for="(question, index) in group.questions"
                            :key="question.id"
                            class="border-b border-slate-100 pb-6 last:border-b-0 last:pb-0"
                        >
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-xs font-semibold uppercase tracking-widest text-slate-400">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <MathText
                                            v-if="question.prompt_text"
                                            :content="question.prompt_text"
                                            class="font-semibold text-slate-900"
                                        />
                                        <span v-else class="font-semibold text-slate-900">(image only)</span>
                                        <span
                                            v-if="completed && question.points !== undefined"
                                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-600"
                                        >
                                            {{ question.score ?? 0 }} / {{ question.points }}
                                        </span>
                                    </div>

                                    <img
                                        v-if="question.image_url"
                                        :src="question.image_url"
                                        class="mt-3 max-h-64 rounded-2xl object-contain"
                                        alt="Question image"
                                    />

                                    <div v-if="question.type === 'multiple_choice'" class="mt-4 space-y-2">
                                        <label
                                            v-for="option in question.options"
                                            :key="option.id"
                                            class="flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 text-sm transition"
                                            :class="Number(answers[question.id]?.selected_option_id) === option.id
                                                ? 'border-indigo-300 bg-indigo-50'
                                                : 'border-slate-200 bg-white hover:border-slate-300'"
                                        >
                                            <input
                                                type="radio"
                                                :name="`q-${question.id}`"
                                                :value="option.id"
                                                :disabled="locked"
                                                v-model="answers[question.id].selected_option_id"
                                                class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            />
                                            <MathText :content="option.label" />
                                        </label>
                                    </div>

                                    <div v-else class="mt-4">
                                        <textarea
                                            v-model="answers[question.id].response_text"
                                            rows="4"
                                            :disabled="locked"
                                            placeholder="Type your answer…"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-indigo-400 focus:bg-white focus:outline-none disabled:opacity-70"
                                        ></textarea>

                                        <div v-if="completed && answers[question.id].response_text" class="mt-2 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                            <MathText :content="answers[question.id].response_text" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!locked" class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                    <p class="text-sm text-slate-500">
                        Review your answers before submitting. Once submitted, they can no longer be edited.
                    </p>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:opacity-40"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting…' : 'Submit answers' }}
                    </button>
                </div>
            </form>
        </div>
    </StudentLayout>
</template>
