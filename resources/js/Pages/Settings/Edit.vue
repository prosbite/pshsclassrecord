<script setup>
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    passingThreshold: {
        type: Number,
        default: 75,
    },
});

const form = useForm({
    passing_threshold: props.passingThreshold,
});

const submit = () => {
    form.put(route('settings.update'));
};
</script>

<template>
    <MainAuthLayout>
        <div class="mx-auto max-w-2xl rounded-3xl bg-white p-6 shadow-lg sm:p-8">
            <div>
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Configuration</p>
                <h1 class="text-2xl font-semibold text-slate-900">Settings</h1>
                <p class="text-sm text-slate-500">
                    Preventive exercises flag learners below this passing threshold. Changing it does not alter stored sessions.
                </p>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <label class="block max-w-xs">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Passing threshold (%)</span>
                    <input
                        v-model.number="form.passing_threshold"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                    />
                    <InputError :message="form.errors.passing_threshold" class="mt-1" />
                </label>

                <div class="flex justify-end">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save settings' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </MainAuthLayout>
</template>
