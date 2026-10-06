<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_logins: 0,
            today_logins: 0,
            week_logins: 0,
            unique_users: 0,
            admin_assisted_logins: 0,
        }),
    },
    topUsers: {
        type: Array,
        default: () => [],
    },
    recentActivities: {
        type: Array,
        default: () => [],
    },
    simulationSummary: {
        type: Object,
        default: () => ({
            total_simulations: 0,
            today_simulations: 0,
            week_simulations: 0,
            unique_students: 0,
        }),
    },
    topStudents: {
        type: Array,
        default: () => [],
    },
    recentSimulations: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('login');

const tabs = [
    { id: 'login', label: 'Login Tracker' },
    { id: 'simulation', label: 'Simulation Tracker' },
];

const formatDateTime = (value) => {
    if (!value) return '—';

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const topLoginCount = computed(() => props.topUsers[0]?.login_count ?? 0);
const topSimulationCount = computed(() => props.topStudents[0]?.simulation_count ?? 0);

const loginMetricCards = computed(() => [
    { label: 'Total sign-ins', value: props.summary.total_logins ?? 0, note: 'All successful logins recorded' },
    { label: 'Today', value: props.summary.today_logins ?? 0, note: 'Logins since midnight' },
    { label: 'This week', value: props.summary.week_logins ?? 0, note: 'Logins in the current week' },
    { label: 'Unique users', value: props.summary.unique_users ?? 0, note: 'Users who have signed in' },
    { label: 'Admin-assisted', value: props.summary.admin_assisted_logins ?? 0, note: 'Logins done through the student switch' },
]);

const simulationMetricCards = computed(() => [
    { label: 'Total simulations', value: props.simulationSummary.total_simulations ?? 0, note: 'Times students entered simulation' },
    { label: 'Today', value: props.simulationSummary.today_simulations ?? 0, note: 'Simulations since midnight' },
    { label: 'This week', value: props.simulationSummary.week_simulations ?? 0, note: 'Simulations in the current week' },
    { label: 'Unique students', value: props.simulationSummary.unique_students ?? 0, note: 'Students who have simulated' },
]);

const progressWidth = (count, max) => {
    if (!max) {
        return '0%';
    }

    return `${Math.max(8, Math.round((count / max) * 100))}%`;
};
</script>

<template>
    <Head title="Tracker" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Tracker</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Activity, counted in one place</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Monitor sign-ins and student simulation usage from a single view.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:w-[28rem]">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Total logins</p>
                            <p class="mt-2 text-3xl font-bold">{{ summary.total_logins ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Total simulations</p>
                            <p class="mt-2 text-3xl font-bold">{{ simulationSummary.total_simulations ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mt-8 flex flex-wrap gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                        :class="activeTab === tab.id
                            ? 'bg-white text-slate-900 shadow'
                            : 'border border-white/20 text-slate-200 hover:bg-white/10'"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </section>

            <!-- Login tracker tab -->
            <template v-if="activeTab === 'login'">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article
                        v-for="card in loginMetricCards"
                        :key="card.label"
                        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">{{ card.label }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ card.value }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ card.note }}</p>
                    </article>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Top users</p>
                                <h2 class="text-lg font-semibold text-slate-900">Most logged-in accounts</h2>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-[0.7rem] uppercase tracking-[0.3em] text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">User</th>
                                        <th class="px-4 py-3">Role</th>
                                        <th class="px-4 py-3">Logins</th>
                                        <th class="px-4 py-3">Last login</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="!topUsers.length">
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
                                            No login activity has been recorded yet.
                                        </td>
                                    </tr>
                                    <tr v-for="(user, index) in topUsers" :key="user.id" class="hover:bg-slate-50">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-700">
                                                    {{ String(index + 1).padStart(2, '0') }}
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-slate-900">{{ user.name }}</p>
                                                    <p class="text-xs text-slate-500">{{ user.username || user.email }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-slate-600">
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">
                                                {{ user.role || 'user' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-slate-900">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="font-semibold">{{ user.login_count }}</span>
                                                    <span class="text-xs text-slate-400">{{ topLoginCount ? Math.round((user.login_count / topLoginCount) * 100) : 0 }}%</span>
                                                </div>
                                                <div class="h-2 rounded-full bg-slate-100">
                                                    <div
                                                        class="h-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-600"
                                                        :style="{ width: progressWidth(user.login_count, topLoginCount) }"
                                                    ></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-slate-600">
                                            {{ formatDateTime(user.last_login_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Recent activity</p>
                                <h2 class="text-lg font-semibold text-slate-900">Latest sign-ins</h2>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-if="!recentActivities.length" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                                Recent login events will appear here as users sign in.
                            </div>

                            <article
                                v-for="activity in recentActivities"
                                :key="activity.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ activity.user?.name }}</p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ activity.actor ? `Signed in through ${activity.actor.name}` : 'Direct sign-in' }}
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        {{ activity.user?.role || 'user' }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <p>{{ formatDateTime(activity.created_at) }}</p>
                                    <p class="sm:text-right">IP {{ activity.ip_address || '—' }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </template>

            <!-- Simulation tracker tab -->
            <template v-else>
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in simulationMetricCards"
                        :key="card.label"
                        class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">{{ card.label }}</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ card.value }}</p>
                        <p class="mt-2 text-sm text-slate-500">{{ card.note }}</p>
                    </article>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Top students</p>
                                <h2 class="text-lg font-semibold text-slate-900">Most simulations</h2>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="text-[0.7rem] uppercase tracking-[0.3em] text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">Student</th>
                                        <th class="px-4 py-3">Simulations</th>
                                        <th class="px-4 py-3">Last simulation</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-if="!topStudents.length">
                                        <td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">
                                            No simulations have been recorded yet.
                                        </td>
                                    </tr>
                                    <tr v-for="(student, index) in topStudents" :key="student.id" class="hover:bg-slate-50">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-sm font-semibold text-slate-700">
                                                    {{ String(index + 1).padStart(2, '0') }}
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-slate-900">{{ student.name }}</p>
                                                    <p class="text-xs text-slate-500">{{ student.username || '—' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-slate-900">
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="font-semibold">{{ student.simulation_count }}</span>
                                                    <span class="text-xs text-slate-400">{{ topSimulationCount ? Math.round((student.simulation_count / topSimulationCount) * 100) : 0 }}%</span>
                                                </div>
                                                <div class="h-2 rounded-full bg-slate-100">
                                                    <div
                                                        class="h-2 rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-600"
                                                        :style="{ width: progressWidth(student.simulation_count, topSimulationCount) }"
                                                    ></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-slate-600">
                                            {{ formatDateTime(student.last_simulation_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Recent activity</p>
                                <h2 class="text-lg font-semibold text-slate-900">Latest simulations</h2>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div v-if="!recentSimulations.length" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                                Simulation events will appear here as students run simulations.
                            </div>

                            <article
                                v-for="activity in recentSimulations"
                                :key="activity.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ activity.student }}</p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            <span v-if="activity.section">{{ activity.section }}</span>
                                            <span v-if="activity.section && activity.quarter"> · </span>
                                            <span v-if="activity.quarter">Quarter {{ activity.quarter }}</span>
                                            <span v-if="!activity.section && !activity.quarter">Simulation session</span>
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">
                                        {{ activity.role || 'student' }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <p>{{ formatDateTime(activity.created_at) }}</p>
                                    <p class="sm:text-right">{{ activity.username || '—' }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </MainAuthLayout>
</template>
