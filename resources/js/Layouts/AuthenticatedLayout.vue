<script setup>
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';

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
                <span
                    :class="[
                        'block h-0.5 rounded-full bg-white transition',
                        menuOpen ? 'translate-y-1 rotate-45' : '-translate-y-0.5',
                    ]"
                ></span>
                <span
                    :class="[
                        'mt-1 block h-0.5 rounded-full bg-white transition',
                        menuOpen ? 'opacity-0' : 'opacity-100',
                    ]"
                ></span>
                <span
                    :class="[
                        'mt-1 block h-0.5 rounded-full bg-white transition',
                        menuOpen ? '-translate-y-1 -rotate-45' : 'translate-y-0.5',
                    ]"
                ></span>
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
                'fixed inset-y-0 left-0 z-30 w-72 overflow-hidden rounded-r-3xl bg-gradient-to-b from-slate-900/90 to-slate-950/80 p-8 shadow-2xl transition-transform lg:relative lg:translate-x-0 lg:w-80',
                menuOpen ? 'translate-x-0' : '-translate-x-full',
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

                <nav class="space-y-3 text-sm">
                    <NavLink
                        :href="route('dashboard')"
                        :active="route().current('dashboard')"
                        class="block rounded-2xl px-3 py-2 text-white hover:bg-white/10"
                        @click="closeMenu"
                    >
                        Dashboard
                    </NavLink>
                </nav>

                <div class="space-y-3 text-sm text-slate-200">
                    <div
                        v-for="item in panelHighlights"
                        :key="item"
                        class="flex gap-3"
                    >
                        <span class="mt-1 h-2 w-2 rounded-full bg-emerald-400"></span>
                        <p>{{ item }}</p>
                    </div>
                </div>

                <div class="mt-auto rounded-2xl border border-white/10 bg-white/5 p-4 text-xs uppercase tracking-[0.4em] text-slate-300">
                    {{ $page.props.auth.user.email }}
                </div>

                <div class="space-y-2 text-xs text-slate-400">
                    <Dropdown align="left" width="36">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl border border-white/10 px-4 py-2 text-left text-sm font-semibold text-white transition hover:border-white/40"
                            >
                                {{ $page.props.auth.user.name }}
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 10.943l3.707-3.707a.75.75 0 111.06 1.06l-4.237 4.237a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </aside>

        <main class="flex flex-1 flex-col lg:ml-80">
            <div class="flex flex-col gap-6 px-4 py-6 lg:px-10 lg:py-10">
                <header
                    class="rounded-2xl bg-white/5 p-6 text-slate-50 shadow-xl backdrop-blur"
                    v-if="$slots.header"
                >
                    <slot name="header" />
                </header>

                <section class="flex-1 overflow-hidden rounded-3xl bg-white/95 p-6 shadow-lg">
                    <slot />
                </section>
            </div>
        </main>
    </div>
</template>
