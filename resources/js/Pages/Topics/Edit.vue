<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    topic: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.topic.name ?? '',
    description: props.topic.description ?? '',
});

const submit = () => {
    form.put(route('topics.update', props.topic.id));
};

const confirmDelete = (questionnaire) => {
    if (!window.confirm(`Delete the questionnaire "${questionnaire.title}"?`)) {
        return;
    }

    router.delete(route('questionnaires.destroy', questionnaire.id), { preserveScroll: true });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Edit topic</h1>
                    </div>
                    <Link
                        :href="route('topics.index')"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Back
                    </Link>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Name</span>
                        <input
                            v-model="form.name"
                            type="text"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </label>

                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Description</span>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        ></textarea>
                        <InputError :message="form.errors.description" class="mt-1" />
                    </label>

                    <div class="flex justify-end">
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Update topic' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div class="flex items-center justify-between px-6 py-5">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Questionnaires</p>
                        <p class="text-sm text-slate-500">{{ (topic.questionnaires || []).length }} in this topic</p>
                    </div>
                    <Link
                        :href="route('questionnaires.create', { topic: topic.id })"
                        class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800"
                    >
                        Add questionnaire
                    </Link>
                </div>
                <div v-if="!(topic.questionnaires || []).length" class="px-6 pb-8 text-sm text-slate-500">
                    No questionnaires yet.
                </div>
                <table v-else class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold text-center">Questions</th>
                            <th class="px-6 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="questionnaire in topic.questionnaires"
                            :key="questionnaire.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ questionnaire.title }}</td>
                            <td class="px-6 py-4 text-center text-slate-700">{{ questionnaire.questions_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <Link
                                        :href="route('questionnaires.edit', questionnaire.id)"
                                        class="text-xs font-semibold uppercase tracking-widest text-sky-600 hover:text-sky-700"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold uppercase tracking-widest text-rose-600 hover:text-rose-700"
                                        @click="confirmDelete(questionnaire)"
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
