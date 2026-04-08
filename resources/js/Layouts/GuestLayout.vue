<script setup>
import { ref } from 'vue';

const menuOpen = ref(false);

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const closeMenu = () => {
    menuOpen.value = false;
};

const panelHighlights = [
    'Secure scholar records and adviser notes.',
    'Attendance, grades, and honors in one view.',
    'Multi-factor ready for PSHS staff accounts.',
];
</script>

<template>
    <div class="relative flex min-h-screen bg-slate-950 text-white">
        <button
            type="button"
            class="absolute top-4 left-4 z-30 inline-flex items-center gap-2 rounded-full border border-white/30 bg-slate-900/70 px-3 py-2 text-sm font-semibold backdrop-blur transition hover:border-white lg:hidden"
            @click="toggleMenu"
        >
            <span class="h-4 w-4">
                <span :class="['block h-0.5 rounded-full bg-white transition', menuOpen ? 'translate-y-1 rotate-45' : '-translate-y-0.5']"></span>
                <span :class="['mt-1 block h-0.5 rounded-full bg-white transition', menuOpen ? 'opacity-0' : 'opacity-100']"></span>
                <span :class="['mt-1 block h-0.5 rounded-full bg-white transition', menuOpen ? '-translate-y-1 -rotate-45' : 'translate-y-0.5']"></span>
            </span>
            Menu
        </button>

        <div
            v-if="menuOpen"
            class="fixed inset-0 z-20 bg-slate-900/70 backdrop-blur lg:hidden"
            @click="closeMenu"
        ></div>

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 w-72 overflow-hidden rounded-r-3xl bg-gradient-to-b from-slate-900/90 to-slate-900/60 p-8 shadow-2xl transition-transform lg:relative lg:translate-x-0 lg:rounded-r-none',
                menuOpen ? 'translate-x-0' : '-translate-x-full',
                'lg:translate-x-0 lg:w-80'
            ]"
        >
            <div class="flex flex-col gap-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.5em] text-slate-400">
                        Philippine Science High School
                    </p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-white">Class Record</h2>
                    <p class="mt-1 text-sm text-slate-300">
                        Access scholar profiles, advisory notes, and attendance logs in one centralized dashboard.
                    </p>
                </div>

                <div class="space-y-3 text-sm">
                    <div v-for="item in panelHighlights" :key="item.title" class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-emerald-400"></span>
                        <p class="text-slate-200">{{ item.text }}</p>
                    </div>
                </div>

                <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs uppercase tracking-[0.4em] text-slate-300">
                    PSHS Portal
                </div>
            </div>
        </aside>

        <main
            class="flex flex-1 flex-col justify-center px-4 py-10 transition-all lg:ml-80 lg:px-12 lg:py-16"
        >
            <slot />
        </main>
    </div>
</template>
