<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    passingThreshold: {
        type: Number,
        default: 75,
    },
    schoolYears: {
        type: Array,
        default: () => [],
    },
    currentSchoolYearId: {
        type: [Number, String],
        default: null,
    },
    currentQuarterId: {
        type: [Number, String],
        default: null,
    },
});

const thresholdForm = useForm({
    passing_threshold: props.passingThreshold,
});

const nextStartYear = computed(() => {
    const latestEnd = props.schoolYears.reduce(
        (max, year) => Math.max(max, Number(year.year_end) || 0),
        0,
    );

    return latestEnd || new Date().getFullYear();
});

const yearForm = useForm({
    year_start: nextStartYear.value,
    year_end: nextStartYear.value + 1,
});

const currentSchoolYear = computed(
    () => props.schoolYears.find(
        (year) => String(year.id) === String(props.currentSchoolYearId),
    ) ?? null,
);

const currentQuarters = computed(() => currentSchoolYear.value?.quarters ?? []);

const formatRange = (start, end) => {
    if (!start || !end) {
        return '—';
    }

    return `${start} – ${end}`;
};

const submitThreshold = () => {
    thresholdForm.put(route('settings.update'), { preserveScroll: true });
};

const createSchoolYear = () => {
    yearForm.post(route('settings.school-years.store'), {
        preserveScroll: true,
        onSuccess: () => {
            yearForm.year_start = Number(yearForm.year_start) + 1;
            yearForm.year_end = Number(yearForm.year_end) + 1;
        },
    });
};

const activateSchoolYear = (year) => {
    if (year.is_current) {
        return;
    }

    router.put(route('settings.school-years.activate', year.id), {}, {
        preserveScroll: true,
    });
};

const activateQuarter = (quarter) => {
    if (String(quarter.id) === String(props.currentQuarterId)) {
        return;
    }

    router.put(route('settings.quarters.activate', quarter.id), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <MainAuthLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Configuration</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Settings</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Manage the school year and its quarters, and tune the grading configuration.
                </p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">School year</h2>
                    <p class="mt-1 text-xs text-slate-400">
                        The current school year scopes every page in the app.
                    </p>
                </div>

                <div class="mt-5 space-y-3">
                    <div
                        v-for="year in schoolYears"
                        :key="year.id"
                        class="flex flex-col gap-3 rounded-2xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        :class="year.is_current
                            ? 'border-emerald-200 bg-emerald-50/60'
                            : 'border-slate-200 bg-white'"
                    >
                        <div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ year.year_start }}–{{ year.year_end }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ year.quarters.length }} quarter{{ year.quarters.length === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="year.is_current"
                                class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-700"
                            >
                                Current
                            </span>
                            <button
                                v-else
                                type="button"
                                class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                                @click="activateSchoolYear(year)"
                            >
                                Set current
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="!schoolYears.length"
                        class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
                    >
                        No school years yet. Create one below.
                    </p>
                </div>

                <form class="mt-6 border-t border-slate-100 pt-6" @submit.prevent="createSchoolYear">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <label class="block sm:w-40">
                            <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">Start year</span>
                            <input
                                v-model.number="yearForm.year_start"
                                type="number"
                                min="2000"
                                max="2100"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                            />
                            <InputError :message="yearForm.errors.year_start" class="mt-1" />
                        </label>
                        <label class="block sm:w-40">
                            <span class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-400">End year</span>
                            <input
                                v-model.number="yearForm.year_end"
                                type="number"
                                min="2001"
                                max="2101"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 focus:border-indigo-400 focus:outline-none"
                            />
                            <InputError :message="yearForm.errors.year_end" class="mt-1" />
                        </label>
                        <PrimaryButton type="submit" :disabled="yearForm.processing">
                            {{ yearForm.processing ? 'Creating…' : 'Add school year' }}
                        </PrimaryButton>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        Creating a school year provisions its four quarters automatically.
                    </p>
                </form>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Current quarter</h2>
                    <p class="mt-1 text-xs text-slate-400">
                        Pages with a quarter selector default to the quarter selected here.
                    </p>
                </div>

                <div v-if="currentQuarters.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="quarter in currentQuarters"
                        :key="quarter.id"
                        type="button"
                        class="rounded-2xl border px-4 py-3 text-left transition"
                        :class="String(quarter.id) === String(currentQuarterId)
                            ? 'border-emerald-300 bg-emerald-50'
                            : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
                        @click="activateQuarter(quarter)"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-slate-900">{{ quarter.name }}</span>
                            <span
                                v-if="String(quarter.id) === String(currentQuarterId)"
                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-700"
                            >
                                Current
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ formatRange(quarter.start_date, quarter.end_date) }}</p>
                    </button>
                </div>
                <p
                    v-else
                    class="mt-4 rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
                >
                    {{ currentSchoolYear ? 'This school year has no quarters.' : 'Set a current school year first.' }}
                </p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Grading</h2>
                    <p class="mt-1 text-xs text-slate-400">
                        Exercises flag learners below this passing threshold. Changing it does not alter stored sessions.
                    </p>
                </div>

                <form class="mt-6 space-y-6" @submit.prevent="submitThreshold">
                    <label class="block max-w-xs">
                        <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Passing threshold (%)</span>
                        <input
                            v-model.number="thresholdForm.passing_threshold"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        />
                        <InputError :message="thresholdForm.errors.passing_threshold" class="mt-1" />
                    </label>

                    <div class="flex justify-end">
                        <PrimaryButton type="submit" :disabled="thresholdForm.processing">
                            {{ thresholdForm.processing ? 'Saving…' : 'Save settings' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </MainAuthLayout>
</template>
