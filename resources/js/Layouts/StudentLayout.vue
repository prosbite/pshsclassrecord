<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user ?? {});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <div class="border-b border-slate-200 bg-white px-6 py-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Student portal</p>
                    <h1 class="text-2xl font-semibold text-slate-900">Welcome back, {{ user.name ?? 'scholar' }}</h1>
                    <p class="text-sm text-slate-500">View your quarterly breakdowns and lockstep with your adviser.</p>
                </div>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-2 text-xs font-semibold uppercase tracking-[0.35em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                >
                    Log Out
                </Link>
            </div>
        </div>
        <main class="mx-auto max-w-6xl px-6 py-10 lg:py-14">
            <slot />
        </main>
    </div>
</template>
