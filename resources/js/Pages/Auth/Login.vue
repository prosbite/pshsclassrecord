<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
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
    <GuestLayout :full-page="true">
        <Head title="Class Record Login" />

        <div class="login-scene">
            <div class="login-scene__bg" aria-hidden="true"></div>
            <div class="login-scene__veil" aria-hidden="true"></div>
            <div class="login-scene__glow login-scene__glow--left" aria-hidden="true"></div>
            <div class="login-scene__glow login-scene__glow--right" aria-hidden="true"></div>

            <div class="relative z-10 w-full max-w-md px-4">
                <div class="login-card rounded-[32px] p-8 md:p-10">
                    <div class="flex flex-col items-center gap-3 text-center">
                        <!-- <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-sky-100 bg-white/80 text-lg font-bold tracking-[0.35em] text-sky-700 shadow-[0_12px_30px_rgba(15,23,42,0.12)]">
                            CR
                        </div> -->
                        <div class="space-y-1">
                            <p class="text-xs uppercase tracking-[0.5em] text-slate-400">SPMSS</p>
                            <h1 class="text-2xl font-semibold text-slate-900 md:text-3xl">Sign in with email</h1>
                            <p class="text-xs leading-6 text-slate-500">
                                Student Performance Monitoring and Simulation System
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="mt-8 space-y-4">
                        <div>
                            <InputLabel for="email" value="Email" class="text-slate-700" />

                            <TextInput
                                id="email"
                                type="email"
                                class="login-input mt-2 block w-full rounded-[16px] border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:outline-none"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="email"
                            />

                            <InputError class="mt-2 text-xs text-rose-600" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" class="text-slate-700" />

                            <TextInput
                                id="password"
                                type="password"
                                class="login-input mt-2 block w-full rounded-[16px] border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:outline-none"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                            />

                            <InputError class="mt-2 text-xs text-rose-600" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between gap-4 text-xs text-slate-500">
                            <label class="flex items-center gap-2">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span>Remember me</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="font-semibold text-slate-700 underline-offset-4 transition hover:text-slate-900"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            class="login-button flex w-full items-center justify-center rounded-[16px] px-6 py-3 text-sm font-semibold text-slate-950 transition disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Sign in
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.login-scene {
    position: relative;
    isolation: isolate;
    display: flex;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #08131f;
}

.login-scene__bg {
    position: absolute;
    inset: 0;
    z-index: -3;
    background-image: url('/img/classroombg.png');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: auto 100%;
    filter: saturate(0.95) contrast(1.02) brightness(1.05);
    transform: scale(1.06);
    animation: login-pan 64s ease-in-out infinite alternate;
    will-change: transform, background-position;
}

.login-scene__veil {
    position: absolute;
    inset: 0;
    z-index: -2;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0.42) 42%, rgba(219, 234, 254, 0.34) 100%),
        radial-gradient(circle at top, rgba(255, 255, 255, 0.36), transparent 34%),
        radial-gradient(circle at bottom, rgba(191, 219, 254, 0.24), transparent 42%);
}

.login-scene__glow {
    position: absolute;
    z-index: -1;
    height: 26rem;
    width: 26rem;
    border-radius: 9999px;
    filter: blur(58px);
    opacity: 0.32;
}

.login-scene__glow--left {
    left: -5rem;
    top: 8rem;
    background: rgba(96, 165, 250, 0.14);
}

.login-scene__glow--right {
    right: -5rem;
    bottom: 4rem;
    background: rgba(147, 197, 253, 0.18);
}

.login-card {
    border: 1px solid rgba(148, 163, 184, 0.28);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.82) 100%);
    box-shadow:
        0 38px 110px rgba(15, 23, 42, 0.2),
        0 0 0 1px rgba(255, 255, 255, 0.55) inset,
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(24px);
    transform: translateY(-2px);
}

.login-input {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}

.login-button {
    color: #ffffff;
    background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 52%, #38bdf8 100%);
    box-shadow:
        0 18px 40px rgba(37, 99, 235, 0.32),
        0 10px 18px rgba(14, 165, 233, 0.18);
    letter-spacing: 0.02em;
}

.login-button:hover:not(:disabled) {
    filter: brightness(1.05);
    transform: translateY(-2px) scale(1.01);
}

@keyframes login-pan {
    0% {
        transform: scale(1.06) translate3d(-2.5%, 0, 0);
    }
    100% {
        transform: scale(1.06) translate3d(2.5%, 0, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .login-scene__bg {
        animation: none;
    }
}
</style>
