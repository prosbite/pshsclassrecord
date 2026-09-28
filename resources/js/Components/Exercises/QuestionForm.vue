<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import MathInsertButton from '@/Components/Exercises/MathInsertButton.vue';
import MathText from '@/Components/Exercises/MathText.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    topics: {
        type: Array,
        default: () => [],
    },
    question: {
        type: Object,
        default: null,
    },
    selectedTopicId: {
        type: Number,
        default: null,
    },
});

const isEdit = computed(() => Boolean(props.question?.id));

const initialOptions = (props.question?.options ?? []).map((option) => ({
    id: option.id,
    label: option.label ?? '',
    is_correct: Boolean(option.is_correct),
}));

const form = useForm({
    topic_id: props.question?.topic_id
        ?? props.selectedTopicId
        ?? props.topics[0]?.id
        ?? null,
    type: props.question?.type ?? 'text',
    prompt_text: props.question?.prompt_text ?? '',
    points: props.question?.points ?? 1,
    answer_key: props.question?.answer_key ?? '',
    position: props.question?.position ?? 0,
    image: null,
    options: initialOptions,
});

if (form.type === 'multiple_choice' && form.options.length === 0) {
    form.options = [
        { label: '', is_correct: true },
        { label: '', is_correct: false },
    ];
}

watch(
    () => form.type,
    (type) => {
        if (type === 'multiple_choice' && form.options.length < 2) {
            form.options = [
                { label: '', is_correct: true },
                { label: '', is_correct: false },
            ];
        }
    }
);

const addOption = () => {
    if (form.options.length >= 10) {
        return;
    }

    form.options.push({ label: '', is_correct: false });
};

const removeOption = (index) => {
    if (form.options.length <= 2) {
        return;
    }

    form.options.splice(index, 1);
};

const markCorrect = (index) => {
    form.options.forEach((option, optionIndex) => {
        option.is_correct = optionIndex === index;
    });
};

const correctCount = computed(() => form.options.filter((option) => option.is_correct).length);

const mcqValid = computed(() => form.options.length >= 2 && correctCount.value === 1);

const canSubmit = computed(() => form.type !== 'multiple_choice' || mcqValid.value);

const hasMath = (text) => /\\\(|\\\[|\$\$/.test(text ?? '');

const previewUrl = ref(props.question?.image_url ?? null);
let objectUrl = null;

const onImageChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.image = file;

    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    if (file) {
        objectUrl = URL.createObjectURL(file);
        previewUrl.value = objectUrl;
    }
};

onBeforeUnmount(() => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
    }
});

const submit = () => {
    if (!canSubmit.value) {
        return;
    }

    if (isEdit.value) {
        form.put(route('questions.update', props.question.id));
        return;
    }

    form.post(route('questions.store'));
};
</script>

<template>
    <div class="mx-auto max-w-3xl rounded-3xl bg-white p-6 shadow-lg sm:p-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                <h1 class="text-2xl font-semibold text-slate-900">
                    {{ isEdit ? 'Edit question' : 'New question' }}
                </h1>
            </div>
            <Link
                :href="route('questions.index')"
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
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Type</span>
                    <select
                        v-model="form.type"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                    >
                        <option value="text">Text (free response)</option>
                        <option value="multiple_choice">Multiple choice</option>
                    </select>
                    <InputError :message="form.errors.type" class="mt-1" />
                </label>
            </div>

            <div class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Prompt text</span>
                <div class="mt-2 flex items-start gap-2">
                    <textarea
                        v-model="form.prompt_text"
                        rows="4"
                        placeholder="Type the question or leave blank if you upload an image."
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                    ></textarea>
                    <MathInsertButton class="mt-1" />
                </div>
                <p v-if="hasMath(form.prompt_text)" class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                    <MathText :content="form.prompt_text" />
                </p>
                <InputError :message="form.errors.prompt_text" class="mt-1" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Image (optional)</span>
                    <input
                        type="file"
                        accept="image/*"
                        class="mt-2 block w-full text-sm text-slate-600"
                        @change="onImageChange"
                    />
                    <InputError :message="form.errors.image" class="mt-1" />
                    <div v-if="previewUrl" class="mt-3">
                        <img :src="previewUrl" class="h-32 w-32 rounded-2xl object-cover" alt="Question image preview" />
                    </div>
                </label>

                <div class="space-y-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Points</span>
                        <input
                            v-model.number="form.points"
                            type="number"
                            min="1"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        />
                        <InputError :message="form.errors.points" class="mt-1" />
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
            </div>

            <div v-if="form.type === 'multiple_choice'" class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Options</span>
                    <span class="text-xs text-slate-500">Mark exactly one correct answer.</span>
                </div>

                <div
                    v-for="(option, index) in form.options"
                    :key="option.id ?? `new-${index}`"
                    class="rounded-2xl border border-slate-100 bg-slate-50 p-3"
                >
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                            <input
                                type="radio"
                                name="correct_option"
                                :checked="option.is_correct"
                                @change="markCorrect(index)"
                            />
                            Correct
                        </label>
                        <input
                            v-model="option.label"
                            type="text"
                            :placeholder="`Option ${index + 1}`"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-900 focus:border-slate-400 focus:outline-none"
                        />
                        <MathInsertButton />
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold text-rose-600 transition hover:text-rose-700 disabled:opacity-30"
                            :disabled="form.options.length <= 2"
                            @click="removeOption(index)"
                        >
                            Remove
                        </button>
                    </div>
                    <p v-if="hasMath(option.label)" class="mt-2 text-sm text-slate-800">
                        <MathText :content="option.label" />
                    </p>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700 disabled:opacity-30"
                        :disabled="form.options.length >= 10"
                        @click="addOption"
                    >
                        Add option
                    </button>
                    <span class="text-xs text-slate-400">{{ form.options.length }} / 10</span>
                </div>

                <InputError :message="form.errors.options" class="mt-1" />
                <p v-if="!mcqValid" class="text-xs text-amber-600">
                    Add at least two options and mark exactly one as correct.
                </p>
            </div>

            <div v-else class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Answer key (grading aid)</span>
                <div class="mt-2 flex items-start gap-2">
                    <textarea
                        v-model="form.answer_key"
                        rows="2"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                    ></textarea>
                    <MathInsertButton class="mt-1" />
                </div>
                <p v-if="hasMath(form.answer_key)" class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                    <MathText :content="form.answer_key" />
                </p>
                <InputError :message="form.errors.answer_key" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3">
                <Link
                    :href="route('questions.index')"
                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                >
                    Cancel
                </Link>
                <PrimaryButton type="submit" :disabled="form.processing || !canSubmit">
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update question' : 'Save question') }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
