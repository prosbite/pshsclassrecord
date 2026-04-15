<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout :full-page="true">
        <Head title="Class Record Login" />

        <div
            class="flex min-h-[calc(100vh-64px)] items-center justify-center px-4 py-10"
            style="background: linear-gradient(180deg, #c6e1ff 0%, #f5fbff 60%, #edf8ff 100%);"
        >
            <div class="relative w-full max-w-md">
                <div class="absolute inset-0 -z-10 rounded-[32px] bg-white/60 blur-[60px]"></div>
                <div class="rounded-[32px] bg-white/70 p-8 shadow-[0_30px_60px_rgba(15,23,42,0.25)] backdrop-blur">
                    <div class="flex flex-col items-center gap-1 text-center">
                        <p class="text-xs uppercase tracking-[0.5em] text-slate-400">Class Record</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Sign in with username</h1>
                        <p class="text-sm text-slate-500">
                            One secure workspace for advisers, scholars, and admins. Keep classroom data current with a
                            verified PSHS account.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="mt-8 space-y-4">
                        <div>
                            <InputLabel for="username" value="Username" />

                            <TextInput
                                id="username"
                                type="text"
                                class="mt-2 block w-full rounded-[16px] border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none"
                                v-model="form.username"
                                required
                                autofocus
                                autocomplete="username"
                            />

                            <InputError class="mt-2 text-xs text-rose-600" :message="form.errors.username" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" />

                            <TextInput
                                id="password"
                                type="password"
                                class="mt-2 block w-full rounded-[16px] border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-indigo-400 focus:bg-white focus:outline-none"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                            />

                            <InputError class="mt-2 text-xs text-rose-600" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <label class="flex items-center gap-2">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span>Remember me</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="font-semibold text-slate-700 underline-offset-4 hover:text-slate-900"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <PrimaryButton
                            class="flex w-full items-center justify-center rounded-[16px] bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                            :class="{ 'opacity-50': form.processing }"
                            :disabled="form.processing"
                        >
                            Sign in
                        </PrimaryButton>
                    </form>

                </div>
            </div>
        </div>
    </GuestLayout>
</template>
