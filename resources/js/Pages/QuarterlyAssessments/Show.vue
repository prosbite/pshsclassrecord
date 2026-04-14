<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    quarterlyAssessment: {
        type: Object,
        required: true,
    },
});

const rawAssessment = computed(() => props.quarterlyAssessment.assessment ?? null);
const tableRows = computed(() => {
    if (!rawAssessment.value) {
        return [];
    }

    if (Array.isArray(rawAssessment.value)) {
        return rawAssessment.value;
    }

    if (Array.isArray(rawAssessment.value.rows)) {
        return rawAssessment.value.rows;
    }

    return [];
});

const storedHeaders = computed(() => {
    if (!rawAssessment.value || Array.isArray(rawAssessment.value)) {
        return [];
    }

    return Array.isArray(rawAssessment.value.headers) ? rawAssessment.value.headers : [];
});

const tableColumns = computed(() => {
    if (storedHeaders.value.length) {
        return storedHeaders.value;
    }

    const firstRow = tableRows.value[0];

    if (!firstRow) {
        return [];
    }

    if (Array.isArray(firstRow)) {
        return firstRow.map((_, index) => `Column ${index + 1}`);
    }

    return Object.keys(firstRow);
});

const getCellValue = (row, column, columnIndex) => {
    if (Array.isArray(row)) {
        return row[columnIndex];
    }

    return row[column];
};

const sectionLabel = computed(() => {
    const section = props.quarterlyAssessment.section;
    if (!section) {
        return 'Unassigned section';
    }

    const grade = section.grade_level?.grade_level;
    const name = section.section_name || 'General';

    return grade ? `${grade} · ${name}` : name;
});

const quarterLabel = computed(() => {
    const quarter = props.quarterlyAssessment.quarter;
    if (!quarter) {
        return '—';
    }

    return quarter.name || `Quarter ${quarter.quarter ?? '—'}`;
});

const schoolYearLabel = computed(() => {
    const schoolYear = props.quarterlyAssessment.schoolYear;
    if (!schoolYear) {
        return '—';
    }

    return `${schoolYear.year_start}-${schoolYear.year_end}`;
});

const createdAtLabel = computed(() => {
    if (!props.quarterlyAssessment.created_at) {
        return 'Date unavailable';
    }

    return new Date(props.quarterlyAssessment.created_at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
});
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Quarterly details</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Quarterly assessment overview</h1>
                        <p class="text-sm text-slate-500">
                            Saved by {{ props.quarterlyAssessment.user?.name ?? 'Unknown' }} on {{ createdAtLabel }}.
                        </p>
                        <div class="flex flex-wrap gap-3 text-[0.7rem] text-slate-500">
                            <span class="rounded-full border border-slate-200 px-3 py-1 uppercase tracking-[0.35em]">
                                {{ sectionLabel }}
                            </span>
                            <span class="rounded-full border border-slate-200 px-3 py-1 uppercase tracking-[0.35em]">
                                {{ quarterLabel }}
                            </span>
                            <span class="rounded-full border border-slate-200 px-3 py-1 uppercase tracking-[0.35em]">
                                {{ schoolYearLabel }}
                            </span>
                        </div>
                    </div>
                    <Link
                        :href="route('quarterly-assessments.index')"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Back to list
                    </Link>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Payload</p>
                        <h2 class="text-lg font-semibold text-slate-900">{{ tableRows.length }} rows</h2>
                    </div>
                </div>

                <div v-if="!tableRows.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                    This quarterly assessment does not contain any rows.
                </div>

                <div v-else class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="min-w-full text-sm text-slate-600">
                        <thead class="bg-slate-50 text-[0.65rem] uppercase tracking-[0.35em] text-slate-500">
                            <tr>
                                <th
                                    v-for="column in tableColumns"
                                    :key="column"
                                    class="px-4 py-3 text-left"
                                >
                                    {{ column }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(row, rowIndex) in tableRows" :key="`row-${rowIndex}`">
                                <td
                                    v-for="(column, columnIndex) in tableColumns"
                                    :key="`${rowIndex}-${column}-${columnIndex}`"
                                    class="px-4 py-3"
                                >
                                    {{ getCellValue(row, column, columnIndex) ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MainAuthLayout>
</template>

