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

const highlights = [
    {
        title: 'Secure PSHS login',
        description:
            'Only Philippine Science High School emails can reach the class record portal, keeping scholar data protected.',
    },
    {
        title: 'Classroom-ready insights',
        description: 'Progress dashboards, attendance notes, and grade snapshots refresh in real time for advisers.',
    },
    {
        title: 'Fast, verifiable access',
        description: 'Two-factor-ready authentication helps maintain the integrity of every record.',
    },
];

const form = useForm({
    email: '',
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
    <GuestLayout>
        <Head title="PSHS Class Record Login" />

        <div class="space-y-10 lg:space-y-12">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.6em] text-slate-300">
                    Philippine Science High School
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-white">
                    PSHS Class Record
                </h1>
                <p class="mt-3 max-w-3xl text-sm text-slate-200">
                    Log in with your verified PSHS account to review scholars, update grades, and prepare for advisory
                    conferences. The class record keeps everything from attendance to honors in one secure space.
                </p>
            </div>

            <div class="grid w-full gap-8 lg:grid-cols-[1.08fr_0.92fr]">
                <section
                    class="rounded-3xl bg-gradient-to-br from-sky-900 via-indigo-900 to-slate-900 p-8 text-white shadow-2xl"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl border border-white/50 bg-white/10 text-lg font-semibold tracking-[0.3em]"
                        >
                            PSHS
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.4em] text-slate-200">Class Record</p>
                            <p class="text-xl font-semibold text-white">Scholars, advisers, and advisors</p>
                        </div>
                    </div>

                    <div class="mt-8 space-y-4 text-sm text-slate-100">
                        <div
                            v-for="item in highlights"
                            :key="item.title"
                            class="flex items-start gap-3"
                        >
                            <span class="mt-1 h-2 w-2 rounded-full bg-emerald-400"></span>
                            <div>
                                <p class="font-semibold text-white">{{ item.title }}</p>
                                <p class="text-xs text-slate-200">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-3xl bg-white/90 p-8 shadow-xl ring-1 ring-slate-900/5 backdrop-blur"
                >
                    <div class="mb-6 text-xs uppercase tracking-[0.45em] text-slate-500">
                        Login
                    </div>

                    <div v-if="status" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-sm font-medium text-emerald-800">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <InputLabel for="email" value="School Email" />

                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full text-black"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                            />

                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" />

                            <TextInput
                                id="password"
                                type="password"
                                class="mt-1 block w-full text-black"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                            />

                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between text-sm text-slate-600">
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
                            class="w-full"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Continue to dashboard
                        </PrimaryButton>
                    </form>
                </section>
            </div>
        </div>
    </GuestLayout>
</template>
