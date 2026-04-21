<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    quarterlyAssessment: {
        type: Object,
        required: true,
    },
});

const normalizeAssessmentPayload = (payload) => {
    if (!payload) {
        return { headers: [], rows: [] };
    }

    const rows = Array.isArray(payload.rows)
        ? payload.rows.map((row) => (Array.isArray(row) ? [...row] : Object.values(row ?? {})))
        : Array.isArray(payload)
            ? payload.map((row) => (Array.isArray(row) ? [...row] : Object.values(row ?? {})))
            : [];

    const headerSource = Array.isArray(payload.headers) ? payload.headers : [];
    const maxColumns = Math.max(headerSource.length, ...rows.map((row) => row.length), 0);

    return {
        headers: Array.from({ length: maxColumns }, (_, index) => String(headerSource[index] ?? `Column ${index + 1}`)),
        rows: rows.map((row) => Array.from({ length: maxColumns }, (_, index) => row[index] ?? '')),
    };
};

const isCalculationHeader = (header) => {
    const normalized = String(header ?? '')
        .trim()
        .replace(/\s+/g, ' ')
        .toLowerCase();

    return [
        't',
        'total',
        '%',
        'percentage',
        'weighted %',
        'w%',
        'tw%',
        'ge',
        'grade equivalent',
        'adjectival',
        'trunc',
        'truncated',
    ].includes(normalized);
};

const isNumericValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return false;
    }

    return Number.isFinite(Number(String(value).replace(/,/g, '')));
};

const rawAssessment = computed(() => props.quarterlyAssessment.assessment ?? null);
const normalizedAssessment = computed(() => normalizeAssessmentPayload(rawAssessment.value));

const editableHeaders = ref([]);
const editableRows = ref([]);
const editMode = ref(false);

const form = useForm({
    assessment: {
        headers: [],
        rows: [],
    },
});

const seedEditor = () => {
    editableHeaders.value = [...normalizedAssessment.value.headers];
    editableRows.value = normalizedAssessment.value.rows.map((row) => [...row]);
};

watch(
    normalizedAssessment,
    () => {
        if (!editMode.value) {
            seedEditor();
        }
    },
    { immediate: true },
);

const tableRows = computed(() => normalizedAssessment.value.rows);
const tableColumns = computed(() => normalizedAssessment.value.headers);

const getCellValue = (row, columnIndex) => row?.[columnIndex];

const sectionLabel = computed(() => {
    const section = props.quarterlyAssessment.section;
    if (!section) {
        return 'Unassigned section';
    }

    const grade = section.grade_level?.grade_level;
    const name = section.section_name || 'General';

    return grade ? `${grade} - ${name}` : name;
});

const quarterLabel = computed(() => {
    const quarter = props.quarterlyAssessment.quarter;
    if (!quarter) {
        return '-';
    }

    return quarter.name || `Quarter ${quarter.quarter ?? '-'}`;
});

const schoolYearLabel = computed(() => {
    const schoolYear = props.quarterlyAssessment.schoolYear;
    if (!schoolYear) {
        return '-';
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

const startEditing = () => {
    seedEditor();
    editMode.value = true;
    form.clearErrors();
};

const cancelEditing = () => {
    editMode.value = false;
    form.clearErrors();
    seedEditor();
};

const buildPayloadForSave = () => {
    const sourceHeaders = normalizedAssessment.value.headers;
    const sourceRows = normalizedAssessment.value.rows;

    const headers = editableHeaders.value.map((header, index) => (
        isCalculationHeader(sourceHeaders[index]) ? sourceHeaders[index] : header
    ));

    const rows = editableRows.value.map((row, rowIndex) => {
        const sourceRow = sourceRows[rowIndex] ?? [];

        return sourceRow.map((value, columnIndex) => (
            isCalculationHeader(sourceHeaders[columnIndex]) ? value : (row[columnIndex] ?? '')
        ));
    });

    return { headers, rows };
};

const saveAssessment = () => {
    form.assessment = buildPayloadForSave();

    form.patch(route('quarterly-assessments.update', props.quarterlyAssessment.id), {
        preserveScroll: true,
        onSuccess: () => {
            editMode.value = false;
        },
    });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Quarterly details</p>
                        <h1 class="text-2xl font-semibold text-slate-900">
                            {{ editMode ? 'Edit quarterly assessment' : 'Quarterly assessment overview' }}
                        </h1>
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

                    <div class="flex flex-wrap gap-3">
                        <Link
                            :href="route('quarterly-assessments.index')"
                            class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Back to list
                        </Link>

                        <SecondaryButton v-if="!editMode" type="button" @click="startEditing">
                            Edit mode
                        </SecondaryButton>
                        <SecondaryButton v-else type="button" @click="cancelEditing">
                            Cancel
                        </SecondaryButton>

                        <PrimaryButton v-if="editMode" type="button" :disabled="form.processing" @click="saveAssessment">
                            {{ form.processing ? 'Saving...' : 'Save changes' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Payload</p>
                        <h2 class="text-lg font-semibold text-slate-900">{{ tableRows.length }} rows</h2>
                    </div>
                    <p class="text-xs uppercase tracking-[0.35em] text-slate-400">
                        {{ editMode ? 'Calculation columns are locked' : 'View mode' }}
                    </p>
                </div>

                <div v-if="!tableRows.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                    This quarterly assessment does not contain any rows.
                </div>

                <div v-else class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="min-w-full text-sm text-slate-600">
                        <thead class="bg-slate-50 text-[0.65rem] uppercase tracking-[0.35em] text-slate-500">
                            <tr>
                                <th
                                    v-for="(column, columnIndex) in tableColumns"
                                    :key="`${columnIndex}-${column}`"
                                    class="px-4 py-3 text-left"
                                >
                                    <div v-if="editMode && !isCalculationHeader(column)" class="space-y-2">
                                        <span class="block text-[0.55rem] uppercase tracking-[0.35em] text-slate-400">
                                            Header {{ columnIndex + 1 }}
                                        </span>
                                        <input
                                            v-model="editableHeaders[columnIndex]"
                                            type="text"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                        />
                                    </div>
                                    <span>{{ column }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(row, rowIndex) in tableRows" :key="`row-${rowIndex}`">
                                <td
                                    v-for="(column, columnIndex) in tableColumns"
                                    :key="`${rowIndex}-${column}-${columnIndex}`"
                                    class="px-4 py-3 align-top"
                                >
                                    <div v-if="editMode && !isCalculationHeader(column) && isNumericValue(getCellValue(row, columnIndex))">
                                        <input
                                            v-model="editableRows[rowIndex][columnIndex]"
                                            type="number"
                                            step="0.01"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                        />
                                    </div>
                                    <span v-else class="block min-h-6">
                                        {{ getCellValue(row, columnIndex) ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="editMode" class="mt-4 text-xs text-slate-500">
                    You can change header labels and numeric score cells. Locked calculation columns will be preserved as-is.
                </p>
                <p v-if="form.errors.assessment" class="mt-3 text-sm text-rose-600">
                    {{ form.errors.assessment }}
                </p>
            </div>
        </div>
    </MainAuthLayout>
</template>
