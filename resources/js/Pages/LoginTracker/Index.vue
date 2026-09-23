<script setup>
import { computed } from 'vue';
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
});

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

const totalLogins = computed(() => props.summary.total_logins ?? 0);
const topLoginCount = computed(() => props.topUsers[0]?.login_count ?? 0);

const metricCards = computed(() => [
    { label: 'Total sign-ins', value: props.summary.total_logins ?? 0, note: 'All successful logins recorded' },
    { label: 'Today', value: props.summary.today_logins ?? 0, note: 'Logins since midnight' },
    { label: 'This week', value: props.summary.week_logins ?? 0, note: 'Logins in the current week' },
    { label: 'Unique users', value: props.summary.unique_users ?? 0, note: 'Users who have signed in' },
    { label: 'Admin-assisted', value: props.summary.admin_assisted_logins ?? 0, note: 'Logins done through the student switch' },
]);

const progressWidth = (count) => {
    if (!topLoginCount.value) {
        return '0%';
    }

    return `${Math.max(8, Math.round((count / topLoginCount.value) * 100))}%`;
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Login tracker</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">User login activity, counted in one place</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Watch overall sign-ins, see which accounts are most active, and spot admin-assisted logins from the same view.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:w-[28rem]">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Total logins</p>
                            <p class="mt-2 text-3xl font-bold">{{ totalLogins }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Top account</p>
                            <p class="mt-2 text-3xl font-bold">{{ topLoginCount }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="card in metricCards"
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
                                                    :style="{ width: progressWidth(user.login_count) }"
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
        </div>
    </MainAuthLayout>
</template>
