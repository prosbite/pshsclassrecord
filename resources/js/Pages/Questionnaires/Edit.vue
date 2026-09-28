<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MathText from '@/Components/Exercises/MathText.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    questionnaire: {
        type: Object,
        required: true,
    },
    topics: {
        type: Array,
        default: () => [],
    },
    availableQuestions: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    topic_id: props.questionnaire.topic_id ?? null,
    title: props.questionnaire.title ?? '',
    instructions: props.questionnaire.instructions ?? '',
    position: props.questionnaire.position ?? 0,
    question_ids: [],
});

const originalTopicId = props.questionnaire.topic_id;

const selectedQuestions = ref([...(props.questionnaire.questions || [])]);
const availableQuestions = ref([...props.availableQuestions]);

const selectedQuestionIds = computed(() => selectedQuestions.value.map((question) => question.id));

const topicChanged = computed(() => Number(form.topic_id) !== Number(originalTopicId));

const label = (question) => question.prompt_text || '(image only)';

watch(
    () => form.topic_id,
    (value, previous) => {
        if (Number(value) === Number(previous)) {
            return;
        }

        if (selectedQuestions.value.length
            && !window.confirm('Changing the topic clears the selected questions. Continue?')) {
            form.topic_id = previous;
            return;
        }

        selectedQuestions.value = [];
        availableQuestions.value = [];
    }
);

const addQuestion = (event) => {
    const id = Number(event.target.value);

    if (!id) {
        return;
    }

    const index = availableQuestions.value.findIndex((question) => question.id === id);

    if (index !== -1) {
        const [question] = availableQuestions.value.splice(index, 1);
        selectedQuestions.value.push(question);
    }

    event.target.value = '';
};

const removeQuestion = (question) => {
    selectedQuestions.value = selectedQuestions.value.filter((item) => item.id !== question.id);
    availableQuestions.value.push(question);
};

const submit = () => {
    form.question_ids = [...selectedQuestionIds.value];

    form.put(route('questionnaires.update', props.questionnaire.id));
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Edit questionnaire</h1>
                    </div>
                    <Link
                        :href="route('questionnaires.index')"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Back
                    </Link>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Topic</span>
                            <select
                                v-model="form.topic_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            >
                                <option value="" disabled>Select topic</option>
                                <option v-for="topic in topics" :key="topic.id" :value="topic.id">{{ topic.name }}</option>
                            </select>
                            <InputError :message="form.errors.topic_id" class="mt-1" />
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Position</span>
                            <input
                                v-model.number="form.position"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            />
                            <InputError :message="form.errors.position" class="mt-1" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Title</span>
                        <input
                            v-model="form.title"
                            type="text"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Instructions</span>
                        <textarea
                            v-model="form.instructions"
                            rows="3"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        ></textarea>
                        <InputError :message="form.errors.instructions" class="mt-1" />
                    </label>

                    <div class="rounded-3xl border border-slate-100 bg-slate-50 p-6 shadow-inner space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Questions</p>
                                <p class="text-sm text-slate-500">
                                    Pick questions from this questionnaire's topic. A question can be reused in other questionnaires.
                                </p>
                            </div>
                            <select
                                v-if="!topicChanged && availableQuestions.length"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none"
                                @change="addQuestion"
                            >
                                <option value="">Add question…</option>
                                <option v-for="question in availableQuestions" :key="question.id" :value="question.id">
                                    {{ label(question) }}
                                </option>
                            </select>
                        </div>

                        <p v-if="topicChanged" class="text-xs text-amber-600">
                            Save the new topic first, then add this topic's questions.
                        </p>

                        <div v-if="!selectedQuestions.length" class="text-xs text-slate-500">
                            No questions in this questionnaire yet.
                        </div>
                        <ul v-else class="space-y-2">
                            <li
                                v-for="question in selectedQuestions"
                                :key="question.id"
                                class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-4 py-2"
                            >
                                <span class="flex items-center gap-2 text-sm text-slate-800">
                                    <span
                                        v-if="question.type === 'multiple_choice'"
                                        class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-700"
                                    >
                                        MCQ
                                    </span>
                                    <MathText v-if="question.prompt_text" :content="question.prompt_text" />
                                    <span v-else>(image only)</span>
                                </span>
                                <div class="inline-flex shrink-0 items-center gap-3">
                                    <Link
                                        :href="route('questions.edit', question.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="removeQuestion(question)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        </ul>
                        <InputError :message="form.errors.question_ids" class="mt-1" />

                        <div class="flex justify-end">
                            <Link
                                :href="route('questions.create', { topic: form.topic_id })"
                                class="text-xs font-semibold uppercase tracking-widest text-slate-600 hover:text-slate-800"
                            >
                                Create a new question
                            </Link>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Update questionnaire' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </MainAuthLayout>
</template>
