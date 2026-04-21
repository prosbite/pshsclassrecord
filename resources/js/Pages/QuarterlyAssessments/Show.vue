<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    buildQuarterlyTablePayload,
    buildQuarterlyTableView,
    parseHeaderMeta,
} from '@/Composables/useQuarterlyAssessmentCalculations.js';

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

const formatCellValue = (cell) => {
    const value = cell?.displayValue ?? cell?.value;

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) {
        return value;
    }

    if (cell?.fieldType === 'percentage' || cell?.fieldType === 'weighted') {
        return `${numericValue.toFixed(2)}%`;
    }

    return numericValue % 1 === 0 ? String(numericValue) : numericValue.toFixed(2);
};

const rawAssessment = computed(() => props.quarterlyAssessment.assessment ?? null);
const normalizedAssessment = computed(() => normalizeAssessmentPayload(rawAssessment.value));

const editableHeaders = ref([]);
const editableRows = ref([]);
const editMode = ref(false);
const activeEditor = ref(null);

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

const isActiveEditor = (key) => activeEditor.value === key;

const activateEditor = (key) => {
    activeEditor.value = key;
};

const deactivateEditor = (key) => {
    if (activeEditor.value === key) {
        activeEditor.value = null;
    }
};

const activeEditorStyle = (key, width) => {
    if (!isActiveEditor(key)) {
        return null;
    }

    return {
        position: 'absolute',
        left: '0',
        top: '50%',
        width,
        minWidth: width,
        maxWidth: 'calc(100vw - 2rem)',
        transform: 'translateY(-50%)',
        zIndex: 30,
    };
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

const tableView = computed(() => buildQuarterlyTableView(editableHeaders.value, editableRows.value));
const tableRows = computed(() => tableView.value.rows);
const tableColumns = computed(() => tableView.value.headers.map((header, index) => ({
    ...parseHeaderMeta(header),
    header,
    index,
})));

const getCell = (rowIndex, columnIndex) => tableRows.value[rowIndex]?.cells[columnIndex] ?? null;

const isCalculationHeader = (header) => parseHeaderMeta(header).fieldType !== 'score';

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
    activeEditor.value = null;
    form.clearErrors();
};

const cancelEditing = () => {
    editMode.value = false;
    activeEditor.value = null;
    form.clearErrors();
    seedEditor();
};

const buildPayloadForSave = () => buildQuarterlyTablePayload(editableHeaders.value, editableRows.value);

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
                        {{ editMode ? 'Calculation columns recalculate live' : 'View mode' }}
                    </p>
                </div>

                <div v-if="!tableRows.length" class="rounded-2xl border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                    This quarterly assessment does not contain any rows.
                </div>

                <div v-else class="overflow-x-auto overflow-y-visible rounded-2xl border border-slate-100">
                    <table class="min-w-full text-sm text-slate-600">
                        <thead class="bg-slate-50 text-[0.65rem] uppercase tracking-[0.35em] text-slate-500">
                            <tr>
                                <th
                                    v-for="(column, columnIndex) in tableColumns"
                                    :key="`header-${columnIndex}`"
                                    :class="[
                                        'relative overflow-visible px-4 py-3 text-left',
                                        isActiveEditor(`header-${columnIndex}`) ? 'z-20' : '',
                                    ]"
                                >
                                    <div v-if="editMode && !isCalculationHeader(column.header)" class="relative space-y-2 overflow-visible">
                                        <span class="block text-[0.55rem] uppercase tracking-[0.35em] text-slate-400">
                                            Header {{ columnIndex + 1 }}
                                        </span>
                                        <input
                                            v-model="editableHeaders[columnIndex]"
                                            type="text"
                                            :class="[
                                                'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none transition-all duration-150',
                                                isActiveEditor(`header-${columnIndex}`)
                                                    ? 'cursor-text shadow-2xl border-sky-400 ring-4 ring-sky-100'
                                                    : 'relative focus:border-sky-400 focus:ring-2 focus:ring-sky-100',
                                            ]"
                                            :style="activeEditorStyle(`header-${columnIndex}`, '28rem')"
                                            @focus="activateEditor(`header-${columnIndex}`)"
                                            @blur="deactivateEditor(`header-${columnIndex}`)"
                                        />
                                    </div>
                                    <span v-else>{{ column.header }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(row, rowIndex) in tableRows" :key="`row-${rowIndex}`">
                                <td
                                    v-for="(column, columnIndex) in tableColumns"
                                    :key="`cell-${rowIndex}-${columnIndex}`"
                                    :class="[
                                        'relative overflow-visible px-4 py-3 align-top',
                                        isActiveEditor(`cell-${rowIndex}-${columnIndex}`) ? 'z-20' : '',
                                    ]"
                                >
                                    <div v-if="editMode && !row.isSubHeader && column.fieldType === 'score'" class="relative overflow-visible">
                                        <input
                                            v-model="editableRows[rowIndex][columnIndex]"
                                            type="number"
                                            step="0.01"
                                            :class="[
                                                'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition-all duration-150',
                                                isActiveEditor(`cell-${rowIndex}-${columnIndex}`)
                                                    ? 'cursor-text shadow-2xl border-sky-400 ring-4 ring-sky-100'
                                                    : 'relative focus:border-sky-400 focus:ring-2 focus:ring-sky-100',
                                            ]"
                                            :style="activeEditorStyle(`cell-${rowIndex}-${columnIndex}`, '24rem')"
                                            @focus="activateEditor(`cell-${rowIndex}-${columnIndex}`)"
                                            @blur="deactivateEditor(`cell-${rowIndex}-${columnIndex}`)"
                                        />
                                    </div>
                                    <span v-else class="block min-h-6">
                                        {{ formatCellValue(getCell(rowIndex, columnIndex)) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="editMode" class="mt-4 text-xs text-slate-500">
                    You can change header labels and numeric score cells. Calculation columns will update automatically.
                </p>
                <p v-if="form.errors.assessment" class="mt-3 text-sm text-rose-600">
                    {{ form.errors.assessment }}
                </p>
            </div>
        </div>
    </MainAuthLayout>
</template>
