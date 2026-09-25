<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    SEGMENT_LABELS,
    buildSummaryRows,
} from '@/Composables/assessmentGrading.js';

const props = defineProps({
    assessments: {
        type: Array,
        default: () => [],
    },
    schoolYear: {
        type: Object,
        default: null,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    section: {
        type: Object,
        default: null,
    },
    sectionFilter: {
        type: String,
        default: 'all',
    },
    quarters: {
        type: Array,
        default: () => [],
    },
    selectedQuarterId: {
        type: [String, Number],
        default: null,
    },
});

const selectedSection = ref(props.sectionFilter ?? 'all');

const sectionOptions = computed(() => {
    const options = props.sections.map((section) => {
        const grade = section.grade_level?.grade_level;
        const name = section.section_name || 'Section';
        const label = grade ? `${grade} · ${name}` : name;
        return { id: String(section.id), label };
    });

    return [
        { id: 'all', label: 'All sections' },
        ...options,
        { id: 'unassigned', label: 'Unassigned section' },
    ];
});

watch(
    () => sectionOptions.value.map((option) => option.id),
    (ids) => {
        if (!ids.includes(selectedSection.value)) {
            selectedSection.value = 'all';
        }
    },
    { immediate: true },
);

watch(
    () => props.sectionFilter,
    (value) => {
        const normalized = value ?? 'all';

        if (normalized !== selectedSection.value) {
            selectedSection.value = normalized;
        }
    },
);

watch(selectedSection, (value) => {
    const normalized = value || 'all';
    const currentFilter = props.sectionFilter ?? 'all';

    if (normalized === currentFilter) {
        return;
    }

    const query = normalized === 'all' ? {} : { section: normalized };

    router.visit(route('assessments.summary'), {
        method: 'get',
        data: query,
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const quarterOptions = computed(() =>
    props.quarters.map((quarter) => ({
        id: String(quarter.id),
        number: quarter.quarter,
        label: quarter.quarter ? `Quarter ${quarter.quarter}` : 'Quarter',
        hasAssessments: props.assessments.some(
            (assessment) => String(assessment.quarter?.id) === String(quarter.id),
        ),
    })),
);

const selectedQuarter = ref(null);

const resolveInitialQuarter = () => {
    if (props.selectedQuarterId !== null && props.selectedQuarterId !== undefined) {
        const requested = quarterOptions.value.find(
            (quarter) => quarter.id === String(props.selectedQuarterId),
        );

        if (requested?.hasAssessments) {
            return requested.id;
        }
    }

    return quarterOptions.value.find((quarter) => quarter.hasAssessments)?.id
        ?? quarterOptions.value[0]?.id
        ?? null;
};

watch(
    quarterOptions,
    (options) => {
        if (!options.some((quarter) => quarter.id === selectedQuarter.value)) {
            selectedQuarter.value = resolveInitialQuarter();
        }
    },
    { immediate: true },
);

const selectedQuarterNumber = computed(() => {
    const match = quarterOptions.value.find((quarter) => quarter.id === selectedQuarter.value);
    return match?.number ?? null;
});

const selectedQuarterLabel = computed(
    () => quarterOptions.value.find((quarter) => quarter.id === selectedQuarter.value)?.label ?? 'Quarter',
);

const learners = computed(() =>
    (props.section?.enrollments ?? [])
        .map((enrollment) => enrollment.learner ?? { id: enrollment.learner_id })
        .filter((learner) => learner?.id),
);

const quarterAssessments = computed(() =>
    props.assessments.filter(
        (assessment) => String(assessment.quarter?.id) === String(selectedQuarter.value),
    ),
);

const previousQuarterAssessments = computed(() => {
    if (selectedQuarterNumber.value === null) {
        return [];
    }

    const previous = quarterOptions.value.find(
        (quarter) => quarter.number === selectedQuarterNumber.value - 1,
    );

    if (!previous) {
        return [];
    }

    return props.assessments.filter(
        (assessment) => String(assessment.quarter?.id) === previous.id,
    );
});

const tableData = computed(() =>
    buildSummaryRows({
        assessments: quarterAssessments.value,
        learners: learners.value,
        previousQuarterAssessments: previousQuarterAssessments.value,
    }),
);

const SEGMENT_KEYS = ['lt1', 'lt2', 'aa', 'fa'];

const SUMMARY_COLUMNS = [
    { key: 'tw', type: 'tw', label: 'TW%' },
    { key: 'ge', type: 'ge', label: 'GE' },
    { key: 'twoThirds', type: 'twoThirds', label: '2/3' },
    { key: 'prevGe', type: 'prevGe', label: 'G' },
    { key: 'oneThird', type: 'oneThird', label: '1/3' },
    { key: 'trunc', type: 'trunc', label: 'Trunc' },
    { key: 'finalGe', type: 'finalGe', label: 'GE' },
];

const segmentScoreLabels = { lt1: 'LT1', lt2: 'LT2', aa: 'AA', fa: 'FA' };

const segmentColumns = computed(() => {
    const grouped = tableData.value.segments ?? {};
    const columns = [];

    SEGMENT_KEYS.forEach((segment) => {
        const assessments = segment === 'lt1'
            ? (grouped.lt1 ? [grouped.lt1] : [])
            : segment === 'lt2'
                ? (grouped.lt2 ? [grouped.lt2] : [])
                : (grouped[segment] ?? []);

        assessments.forEach((assessment, index) => {
            columns.push({
                key: `${segment}-score-${assessment.id}`,
                segment,
                type: 'score',
                assessmentId: assessment.id,
                label: assessments.length > 1
                    ? `${segmentScoreLabels[segment]}${index + 1}`
                    : segmentScoreLabels[segment],
                perfectScore: assessment.perfect_score,
            });
        });

        columns.push({ key: `${segment}-total`, segment, type: 'total', label: 'T' });
        columns.push({ key: `${segment}-percent`, segment, type: 'percentage', label: '%' });
        columns.push({ key: `${segment}-weighted`, segment, type: 'weighted', label: 'W%', weighted: true });
    });

    return columns;
});

const bodyColumns = computed(() => [...segmentColumns.value, ...SUMMARY_COLUMNS]);

const segmentGroupSpans = computed(() => {
    const spans = {};

    segmentColumns.value.forEach((column) => {
        spans[column.segment] = (spans[column.segment] ?? 0) + 1;
    });

    return spans;
});

const hasSection = computed(() => Boolean(props.section));

const formatNumber = (value, digits = 2) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue.toFixed(digits) : '—';
};

const formatPercent = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? `${numericValue.toFixed(2)}%` : '—';
};

const cellValue = (row, column) => {
    if (column.type === 'score') {
        return formatNumber(row.scores?.[column.assessmentId]);
    }

    const metrics = row.segments?.[column.segment];

    if (column.type === 'total') {
        return formatNumber(metrics?.score);
    }

    if (column.type === 'percentage') {
        return formatPercent(metrics?.percentage);
    }

    if (column.type === 'weighted') {
        return formatPercent(metrics?.weighted);
    }

    if (column.type === 'tw') {
        return formatPercent(row.summary?.twPercent);
    }

    if (column.type === 'ge') {
        return formatNumber(row.summary?.currentGe);
    }

    if (column.type === 'twoThirds') {
        return formatNumber(row.summary?.currentThird);
    }

    if (column.type === 'prevGe') {
        return formatNumber(row.summary?.previousGe);
    }

    if (column.type === 'oneThird') {
        return formatNumber(row.summary?.previousThird);
    }

    if (column.type === 'trunc') {
        return formatNumber(row.summary?.trunc, 3);
    }

    if (column.type === 'finalGe') {
        return formatNumber(row.summary?.finalGe);
    }

    return '—';
};

const isTentative = (row, column) =>
    column.type === 'score' && Boolean(row.tentatives?.[column.assessmentId]);

const hasAnyTentative = computed(() => {
    const currentIds = Object.values(tableData.value.segments ?? {})
        .flat()
        .filter(Boolean)
        .map((assessment) => assessment.id);

    return tableData.value.rows.some((row) =>
        currentIds.some((id) => Boolean(row.tentatives?.[id])));
});

const learnerName = (learner) => ({
    familyName: learner.last_name ?? '',
    givenName: learner.first_name ?? '',
    middleInitial: learner.middle_name ? `${learner.middle_name.trim().charAt(0)}.` : '',
});
</script>

<template>
    <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Assessment summary</p>
                <h3 class="text-lg font-semibold text-slate-900">Quarterly breakdown</h3>
                <span class="text-xs text-slate-500">
                    {{ quarterAssessments.length }} assessments · {{ schoolYear ? schoolYear.year_start + '-' + schoolYear.year_end : 'No school year' }}
                </span>
            </div>
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2">
                    <label for="summary-section-filter" class="sr-only">Filter summary by section</label>
                    <select
                        id="summary-section-filter"
                        v-model="selectedSection"
                        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-[0.65rem] font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option
                            v-for="option in sectionOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <div v-if="hasSection" class="flex flex-wrap gap-2">
                    <button
                        v-for="quarter in quarterOptions"
                        :key="quarter.id"
                        type="button"
                        class="rounded-2xl px-4 py-2 text-xs font-semibold uppercase tracking-widest transition-all focus:outline-none"
                        :class="[
                            quarter.id === selectedQuarter
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                            !quarter.hasAssessments && 'opacity-40 cursor-not-allowed',
                        ]"
                        :disabled="!quarter.hasAssessments"
                        @click="selectedQuarter = quarter.id"
                    >
                        {{ quarter.label }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!hasSection" class="px-6 py-16 text-center">
            <p class="text-slate-400">Select a section to view its quarterly breakdown.</p>
            <p class="mt-2 text-sm text-slate-500">A summary needs a concrete section; "All sections" and "Unassigned" are not supported here.</p>
        </div>

        <div v-else class="px-6 pb-4">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full">
                    <table class="min-w-full divide-y divide-slate-100 text-xs text-slate-600 table-fixed">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr class="border-b border-slate-200">
                                <th rowspan="2" colspan="3" class="px-4 py-3 text-left font-semibold border-r border-slate-200 w-40 sticky left-0 z-10 bg-white">
                                    Mathematics 6
                                </th>
                                <template v-for="segment in SEGMENT_KEYS" :key="segment">
                                    <th
                                        v-if="segmentGroupSpans[segment]"
                                        :colspan="segmentGroupSpans[segment] - 1"
                                        class="px-4 py-3 text-center font-semibold border-r border-slate-200"
                                    >
                                        {{ SEGMENT_LABELS[segment] }}
                                    </th>
                                    <th
                                        v-if="segmentGroupSpans[segment]"
                                        class="px-4 py-3 text-center font-semibold border-r border-slate-200"
                                    >
                                        25%
                                    </th>
                                </template>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200"></th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    {{ selectedQuarterLabel }}
                                </th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    Previous Quarter
                                </th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    Final Grade
                                </th>
                                <th rowspan="2" class="px-6 py-3 text-center font-semibold border-l border-slate-200 bg-amber-50 w-40">
                                    Adjectival Equivalent
                                </th>
                            </tr>
                            <tr class="text-[10px] border-b border-slate-100 bg-white text-slate-500">
                                <template v-for="column in segmentColumns" :key="column.key">
                                    <th class="px-2 py-2 border-r border-slate-100" :class="{ 'font-medium text-emerald-500': column.weighted }">
                                        <span class="block">{{ column.label }}</span>
                                        <span v-if="column.type === 'score' && column.perfectScore !== null" class="block text-[9px] text-slate-400">
                                            {{ column.perfectScore }}
                                        </span>
                                    </th>
                                </template>
                                <th
                                    v-for="column in SUMMARY_COLUMNS"
                                    :key="column.key"
                                    class="px-2 py-2 border-r border-slate-100"
                                    :class="{ 'font-bold text-gray-800': column.type === 'tw' }"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                            <tr
                                v-for="row in tableData.rows"
                                :key="row.learnerId"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 border-r border-slate-100 font-medium sticky left-0 bg-white z-10">
                                    {{ learnerName(row.learner).familyName }}
                                </td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">
                                    {{ learnerName(row.learner).givenName }}
                                </td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">
                                    {{ learnerName(row.learner).middleInitial }}
                                </td>
                                <td
                                    v-for="column in bodyColumns"
                                    :key="column.key"
                                    class="px-3 py-3 text-center border-r border-slate-100"
                                    :class="[
                                        { 'text-emerald-500 font-medium': column.weighted },
                                        isTentative(row, column) ? 'bg-amber-50 text-amber-700 font-semibold' : '',
                                    ]"
                                >
                                    {{ cellValue(row, column) }}
                                    <span
                                        v-if="isTentative(row, column)"
                                        class="ml-1 text-[9px] font-bold uppercase text-amber-600"
                                    >
                                        T
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center border-r border-slate-100 font-medium text-emerald-500">
                                    {{ row.summary?.adjectival ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!tableData.rows.length" class="bg-white">
                                <td :colspan="bodyColumns.length + 4" class="px-4 py-6 text-center text-sm text-slate-400">
                                    No learners are enrolled in this section for the active school year.
                                </td>
                            </tr>
                            <tr v-else-if="!quarterAssessments.length" class="bg-white">
                                <td :colspan="bodyColumns.length + 4" class="px-4 py-6 text-center text-sm text-slate-400">
                                    No assessments recorded for {{ selectedQuarterLabel }} yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <p v-if="hasAnyTentative" class="mt-2 text-[10px] font-semibold uppercase tracking-widest text-amber-600">
                T = tentative score
            </p>
        </div>
    </div>
</template>

<style>
    .table-fixed {
        table-layout: fixed;
    }
</style>
