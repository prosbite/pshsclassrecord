<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const form = useForm({
    name: '',
    description: '',
});

const submit = () => {
    form.post(route('topics.store'));
};
</script>

<template>
    <MainAuthLayout>
        <div class="mx-auto max-w-2xl rounded-3xl bg-white p-6 shadow-lg sm:p-8">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Exercise bank</p>
                    <h1 class="text-2xl font-semibold text-slate-900">New topic</h1>
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

                <div class="flex justify-end gap-3">
                    <Link
                        :href="route('topics.index')"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save topic' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </MainAuthLayout>
</template>
