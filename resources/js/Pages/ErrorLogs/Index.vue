<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ source: 'db' }),
    },
    summary: {
        type: Object,
        default: () => ({ total: 0, today: 0, week: 0 }),
    },
    logs: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
    logFiles: {
        type: Array,
        default: () => [],
    },
    selectedFile: {
        type: String,
        default: null,
    },
    fileEntries: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const source = ref(props.filters?.source === 'files' ? 'files' : 'db');

const filterForm = ref({
    search: props.filters?.search ?? '',
    level: props.filters?.level ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    file: props.selectedFile ?? props.filters?.file ?? '',
});

const expandedId = ref(null);
const expandedEntry = ref(null);

const buildParams = (extra = {}) => {
    const params = { source: source.value, ...extra };

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === null || params[key] === '') {
            delete params[key];
        }
    });

    return params;
};

const visit = (overrides = {}) => {
    const params = buildParams({
        search: filterForm.value.search,
        level: filterForm.value.level,
        date_from: filterForm.value.date_from,
        date_to: filterForm.value.date_to,
        file: source.value === 'files' ? filterForm.value.file : undefined,
        ...overrides,
    });

    router.get(route('error-logs.index'), params, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const switchSource = (value) => {
    if (source.value === value) {
        return;
    }

    source.value = value;
    expandedId.value = null;
    expandedEntry.value = null;

    if (value === 'files' && !filterForm.value.file && props.logFiles.length) {
        filterForm.value.file = props.logFiles[0].name;
    }

    visit({ source: value });
};

const applyFilters = () => visit({ source: source.value });

const resetFilters = () => {
    filterForm.value = { ...filterForm.value, search: '', level: '', date_from: '', date_to: '' };
    visit({ source: source.value, search: '', level: '', date_from: '', date_to: '' });
};

const selectFile = (event) => {
    filterForm.value.file = event.target.value;
    expandedEntry.value = null;
    visit({ source: 'files', file: filterForm.value.file });
};

const toggleRow = (log) => {
    expandedId.value = expandedId.value === log.id ? null : log.id;
};

const toggleEntry = (index) => {
    expandedEntry.value = expandedEntry.value === index ? null : index;
};

const deleteRow = (log) => {
    if (!window.confirm('Delete this error log entry? This cannot be undone.')) {
        return;
    }

    router.delete(route('error-logs.destroy', log.id), { preserveScroll: true });
};

const clearAll = () => {
    const total = props.logs?.total ?? props.logs?.data?.length ?? 0;

    if (!total) {
        return;
    }

    if (!window.confirm(`Delete all ${total} error log entries? This cannot be undone.`)) {
        return;
    }

    router.delete(route('error-logs.destroy-all'), { preserveScroll: true });
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const levelClasses = (level) => ({
    critical: 'bg-red-100 text-red-700',
    error: 'bg-amber-100 text-amber-700',
}[String(level).toLowerCase()] ?? 'bg-slate-100 text-slate-600');

const statusClasses = (status) => (Number(status) >= 500 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600');

const formatBytes = (bytes) => {
    if (!bytes) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

    return `${(bytes / (1024 ** exponent)).toFixed(exponent ? 1 : 0)} ${units[exponent]}`;
};

const links = computed(() => props.logs?.links ?? []);
const hasPagination = computed(() => links.value.length > 3);
</script>

<template>
    <Head title="Error Logs" />

    <MainAuthLayout>
        <div class="space-y-6">
            <section class="rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-700 p-6 text-white shadow-xl sm:p-8">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Diagnostics</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Error Logs</h1>
                        <p class="mt-4 text-sm leading-6 text-slate-300 sm:text-base">
                            Captured 5xx exceptions plus a read-only view of the raw log files, all in one place.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:w-[30rem]">
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Total</p>
                            <p class="mt-2 text-3xl font-bold">{{ summary.total ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">Today</p>
                            <p class="mt-2 text-3xl font-bold">{{ summary.today ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-300">7 days</p>
                            <p class="mt-2 text-3xl font-bold">{{ summary.week ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                        :class="source === 'db' ? 'bg-white text-slate-900 shadow' : 'border border-white/20 text-slate-200 hover:bg-white/10'"
                        @click="switchSource('db')"
                    >
                        Database
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-5 py-2 text-xs font-semibold uppercase tracking-widest transition"
                        :class="source === 'files' ? 'bg-white text-slate-900 shadow' : 'border border-white/20 text-slate-200 hover:bg-white/10'"
                        @click="switchSource('files')"
                    >
                        Log files
                    </button>
                </div>
            </section>

            <div v-if="flash.success" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-6 py-4 text-sm font-medium text-emerald-700">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-3xl border border-red-200 bg-red-50 px-6 py-4 text-sm font-medium text-red-700">
                {{ flash.error }}
            </div>

            <!-- Database tab -->
            <section v-if="source === 'db'" class="space-y-6">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Search</span>
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    placeholder="Message, class or URL"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                    @keyup.enter="applyFilters"
                                />
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Level</span>
                                <select
                                    v-model="filterForm.level"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                    @change="applyFilters"
                                >
                                    <option value="">All levels</option>
                                    <option value="error">Error</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">From</span>
                                <input
                                    v-model="filterForm.date_from"
                                    type="date"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                />
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">To</span>
                                <input
                                    v-model="filterForm.date_to"
                                    type="date"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                />
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="rounded-full bg-indigo-600 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow transition hover:bg-indigo-500"
                                @click="applyFilters"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Captured errors</p>
                            <h2 class="text-lg font-semibold text-slate-900">{{ logs.total ?? logs.data?.length ?? 0 }} entries</h2>
                        </div>
                        <button
                            v-if="(logs.total ?? logs.data?.length ?? 0) > 0"
                            type="button"
                            class="rounded-full border border-red-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:bg-red-50"
                            @click="clearAll"
                        >
                            Clear all
                        </button>
                    </div>

                    <div v-if="!logs.data?.length" class="mt-6 rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                        No captured errors match the current filters.
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <article
                            v-for="log in logs.data"
                            :key="log.id"
                            class="rounded-2xl border border-slate-200 bg-slate-50"
                        >
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em]" :class="levelClasses(log.level)">
                                            {{ log.level }}
                                        </span>
                                        <span class="rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em]" :class="statusClasses(log.status_code)">
                                            {{ log.status_code }}
                                        </span>
                                        <span class="truncate text-xs font-mono text-slate-500">{{ log.exception_class }}</span>
                                    </div>
                                    <p class="mt-3 line-clamp-2 text-sm font-medium text-slate-900">{{ log.message }}</p>
                                    <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500">
                                        <span>{{ formatDateTime(log.created_at) }}</span>
                                        <span v-if="log.url" class="truncate">→ {{ log.method }} {{ log.url }}</span>
                                        <span v-if="log.user?.name">User: {{ log.user.name }} ({{ log.user.role || '—' }})</span>
                                        <span v-if="log.ip_address">IP {{ log.ip_address }}</span>
                                        <span v-if="log.file">at {{ log.file }}:{{ log.line }}</span>
                                    </div>
                                </div>

                                <div class="flex flex-none items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-100"
                                        @click="toggleRow(log)"
                                    >
                                        {{ expandedId === log.id ? 'Hide' : 'Details' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-red-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:bg-red-50"
                                        @click="deleteRow(log)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>

                            <div v-if="expandedId === log.id" class="border-t border-slate-200 bg-white p-4">
                                <div class="grid gap-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                                    <p><span class="font-semibold text-slate-500">Route:</span> {{ log.route_name || '—' }}</p>
                                    <p><span class="font-semibold text-slate-500">Code:</span> {{ log.code || '—' }}</p>
                                    <p><span class="font-semibold text-slate-500">Env:</span> {{ log.context?.app_env || '—' }}</p>
                                    <p><span class="font-semibold text-slate-500">PHP:</span> {{ log.context?.php_version || '—' }}</p>
                                </div>
                                <p v-if="log.user_agent" class="mt-3 break-all text-xs text-slate-500">{{ log.user_agent }}</p>
                                <pre class="mt-4 max-h-96 overflow-auto rounded-2xl bg-slate-900 p-4 text-xs leading-5 text-slate-100">{{ log.stack_trace }}</pre>
                            </div>
                        </article>
                    </div>

                    <div v-if="hasPagination" class="mt-6 flex flex-wrap items-center gap-2">
                        <template v-for="(link, index) in links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                                :class="link.active ? 'bg-indigo-600 text-white shadow' : 'border border-slate-200 text-slate-600 hover:bg-slate-50'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="rounded-full px-4 py-1.5 text-xs font-semibold text-slate-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </section>

            <!-- Files tab -->
            <section v-else class="space-y-6">
                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="grid flex-1 gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Log file</span>
                                <select
                                    :value="filterForm.file"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                    @change="selectFile"
                                >
                                    <option v-if="!logFiles.length" value="">No log files found</option>
                                    <option v-for="file in logFiles" :key="file.name" :value="file.name">
                                        {{ file.name }} ({{ formatBytes(file.size) }})
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Level</span>
                                <select
                                    v-model="filterForm.level"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                    @change="applyFilters"
                                >
                                    <option value="">All levels</option>
                                    <option value="ERROR">Error</option>
                                    <option value="CRITICAL">Critical</option>
                                    <option value="WARNING">Warning</option>
                                    <option value="DEBUG">Debug</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Search</span>
                                <input
                                    v-model="filterForm.search"
                                    type="text"
                                    placeholder="Message or trace"
                                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                                    @keyup.enter="applyFilters"
                                />
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="rounded-full bg-indigo-600 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow transition hover:bg-indigo-500"
                                @click="applyFilters"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-400">Read-only view. Newest entries are shown first; the log file is never modified.</p>
                </div>

                <div class="rounded-3xl bg-white p-6 shadow-lg">
                    <div v-if="!fileEntries.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                        {{ selectedFile ? 'No entries match the current filters.' : 'No log file selected.' }}
                    </div>

                    <div v-else class="space-y-3">
                        <article
                            v-for="(entry, index) in fileEntries"
                            :key="`${entry.timestamp}-${index}`"
                            class="rounded-2xl border border-slate-200 bg-slate-50"
                        >
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.2em]" :class="levelClasses(entry.level)">
                                            {{ entry.level }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ entry.timestamp }}</span>
                                    </div>
                                    <p class="mt-3 line-clamp-2 break-all text-sm font-medium text-slate-900">{{ entry.message }}</p>
                                </div>
                                <button
                                    v-if="entry.trace"
                                    type="button"
                                    class="flex-none rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-100"
                                    @click="toggleEntry(index)"
                                >
                                    {{ expandedEntry === index ? 'Hide' : 'Trace' }}
                                </button>
                            </div>

                            <pre
                                v-if="entry.trace && expandedEntry === index"
                                class="max-h-96 overflow-auto border-t border-slate-200 bg-slate-900 p-4 text-xs leading-5 text-slate-100"
                            >{{ entry.trace }}</pre>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </MainAuthLayout>
</template>
