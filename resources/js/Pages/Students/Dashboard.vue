<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';
import {
    buildQuarterResult,
    buildQuarterResultFromEntries,
    buildSegmentEntries,
    detailEntriesForSegment as filterDetailEntries,
    groupAssessmentsBySegment,
    hasSegmentAssessments,
} from '@/Composables/assessmentGrading.js';
import { getAdjectivalEquivalent } from '@/Composables/utilities.js';

const props = defineProps({
    student: { type: Object, default: null },
    section: { type: Object, default: null },
    schoolYear: { type: Object, default: null },
    assessments: { type: Array, default: () => [] },
});

const studentName = computed(() => {
    if (!props.student) return 'Scholar';

    const parts = [
        props.student.last_name,
        props.student.first_name,
    ].filter(Boolean).map(part => part.trim());

    return parts.length ? parts.join(', ') : (props.student.email ?? 'Scholar');
});

const currentSectionLabel = computed(() => {
    if (!props.section?.section_name) return 'Section not assigned';
    return props.section.grade_level
        ? `${props.section.grade_level} · ${props.section.section_name}`
        : props.section.section_name;
});

const currentYearLabel = computed(() => {
    if (!props.schoolYear?.year_start) return 'School year not available';
    return `${props.schoolYear.year_start}-${props.schoolYear.year_end}`;
});

const hasAssessments = computed(() => props.assessments.length > 0);

const quarterOrder = [1, 2, 3, 4];
const quarterLabels = {
    1: '1st Quarter',
    2: '2nd Quarter',
    3: '3rd Quarter',
    4: '4th Quarter',
};

const learnerId = computed(() => props.student?.id ?? null);

const scoresByLearner = computed(() => {
    const map = {};
    const id = learnerId.value;

    if (id === null || id === undefined) {
        return map;
    }

    map[id] = {};
    props.assessments.forEach((assessment) => {
        map[id][assessment.id] = assessment.score ?? null;
    });

    return map;
});

const tentativesByLearner = computed(() => {
    const map = {};
    const id = learnerId.value;

    if (id === null || id === undefined) {
        return map;
    }

    map[id] = {};
    props.assessments.forEach((assessment) => {
        map[id][assessment.id] = Boolean(assessment.tentative);
    });

    return map;
});

const quarterAssessmentsByIndex = computed(() => {
    const mapping = quarterOrder.reduce((acc, quarter) => { acc[quarter] = []; return acc; }, {});

    props.assessments.forEach((assessment) => {
        const quarterIndex = Number(assessment.quarter?.quarter ?? null);
        if (!quarterOrder.includes(quarterIndex)) return;

        mapping[quarterIndex].push(assessment);
    });

    return mapping;
});

const groupedByQuarter = computed(() => Object.fromEntries(
    Object.entries(quarterAssessmentsByIndex.value).map(([quarter, assessments]) => [
        quarter,
        groupAssessmentsBySegment(assessments),
    ]),
));

const availableQuarters = computed(() =>
    quarterOrder.filter((quarter) => quarterAssessmentsByIndex.value[quarter]?.length));

const selectedQuarter = ref(4);
watch(
    availableQuarters,
    (available) => {
        if (!available.includes(selectedQuarter.value)) {
            selectedQuarter.value = available.length ? available[available.length - 1] : 4;
        }
    },
    { immediate: true },
);

const selectedGrouped = computed(() => groupedByQuarter.value[selectedQuarter.value] ?? null);
const hasSelectedQuarter = computed(() => hasSegmentAssessments(selectedGrouped.value));

const segmentEntries = computed(() =>
    buildSegmentEntries(selectedGrouped.value, scoresByLearner.value, learnerId.value, tentativesByLearner.value));

const hasSegmentEntries = computed(() =>
    Object.values(segmentEntries.value).some((entries) => entries.length));

const previousQuarterGe = computed(() => {
    const previousGrouped = groupedByQuarter.value[selectedQuarter.value - 1];

    if (!hasSegmentAssessments(previousGrouped)) {
        return null;
    }

    return buildQuarterResult(
        previousGrouped,
        scoresByLearner.value,
        learnerId.value,
        null,
        tentativesByLearner.value,
    ).currentGe;
});

const selectedQuarterResult = computed(() => {
    if (!hasSelectedQuarter.value) {
        return null;
    }

    return buildQuarterResult(
        selectedGrouped.value,
        scoresByLearner.value,
        learnerId.value,
        previousQuarterGe.value,
        tentativesByLearner.value,
    );
});

const formatTwoDecimals = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numericValue = Number(value);
    if (Number.isFinite(numericValue)) {
        return numericValue.toFixed(2);
    }

    return value;
};

const formatAssessmentValue = (label, value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const normalizedLabel = (label ?? '').toString().trim().toLowerCase();
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return value;
    }

    if (normalizedLabel === 'percentage' || normalizedLabel === 'weighted %') {
        const percentageValue = Math.abs(numericValue) <= 1 ? numericValue * 100 : numericValue;
        return `${percentageValue.toFixed(2)}%`;
    }

    return numericValue.toFixed(2);
};

const formatAssessmentOverallValue = (label, value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const normalizedLabel = (label ?? '').toString().trim().toLowerCase();
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return value;
    }

    if (normalizedLabel === 'percentage' || normalizedLabel === 'weighted %') {
        const percentageValue = Math.abs(numericValue) <= 1 ? numericValue * 100 : numericValue;
        return `${percentageValue.toFixed(2)}%`;
    }

    return numericValue.toFixed(2);
};

const formatPreciseDecimal = (value, digits = 2) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const numericValue = Number(value);
    if (!Number.isFinite(numericValue)) {
        return value;
    }

    return numericValue.toFixed(digits);
};

const isScoreEntry = (entry) => entry?.fieldType === 'score';

const toNumericValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numericValue = Number(value);
    return Number.isFinite(numericValue) ? numericValue : null;
};

const formatEditableValue = (value) => {
    const numericValue = toNumericValue(value);
    return numericValue === null ? '' : String(numericValue);
};

const getSimulationValue = (entry) => {
    if (!simulationMode.value || !isScoreEntry(entry)) {
        return entry.value;
    }

    if (!Object.prototype.hasOwnProperty.call(simulationDraft, entry.key)) {
        return entry.value;
    }

    const draftValue = simulationDraft[entry.key];
    return draftValue === '' || draftValue === null || draftValue === undefined
        ? 0
        : draftValue;
};

const displayEntryValue = (segment, entry) => {
    if (!simulationMode.value) {
        return entry.value;
    }

    const metrics = simulatedQuarterResult.value?.segments?.[segment];

    if (entry.fieldType === 'score') {
        return getSimulationValue(entry);
    }

    if (entry.fieldType === 'total') {
        return metrics?.score;
    }

    if (entry.fieldType === 'percentage') {
        return metrics?.percentage;
    }

    if (entry.fieldType === 'weighted') {
        return metrics?.weighted;
    }

    return entry.value;
};

const detailEntriesForSegment = (segment) =>
    filterDetailEntries(segmentEntries.value[segment] ?? [], segment);

const formatLabel = (label) => {
    const normalized = (label ?? '').replace(/_/g, ' ').trim();
    if (!normalized) return label;
    return normalized
        .split(' ')
        .map((word) => `${word.charAt(0).toUpperCase()}${word.slice(1)}`)
        .join(' ');
};

const entryLabel = (segment, label, index) => {
    if ((segment === 'lt1' || segment === 'lt2') && index === 0) {
        return 'Score';
    }

    return formatLabel(label);
};

const segmentConfig = {
    lt1: { title: 'Long Test 1', accent: 'from-emerald-50 to-white' },
    lt2: { title: 'Long Test 2', accent: 'from-sky-50 to-white' },
    aa: { title: 'Alternative Assessments', accent: 'from-amber-50 to-white' },
    fa: { title: 'Formative Assessments', accent: 'from-slate-50 to-white' },
    final: { title: 'Quarterly Summary', accent: 'from-indigo-50 to-white' },
};

const detailSegments = ['lt1', 'lt2', 'aa', 'fa'];

const simulationMode = ref(false);
const simulationDraft = reactive({});

const clearSimulationDraft = () => {
    Object.keys(simulationDraft).forEach((key) => {
        delete simulationDraft[key];
    });
};

const initializeSimulationDraft = () => {
    clearSimulationDraft();

    Object.values(segmentEntries.value).flat().forEach((entry) => {
        if (entry.fieldType === 'score') {
            simulationDraft[entry.key] = formatEditableValue(entry.value);
        }
    });
};

watch(
    [segmentEntries, simulationMode],
    ([, enabled]) => {
        if (enabled) {
            initializeSimulationDraft();
            return;
        }

        clearSimulationDraft();
    },
    { immediate: true },
);

const simulationEntries = computed(() =>
    Object.fromEntries(
        Object.entries(segmentEntries.value).map(([segment, entries]) => [
            segment,
            entries.map((entry) => ({ ...entry, value: getSimulationValue(entry) })),
        ]),
    ));

const simulatedQuarterResult = computed(() => {
    if (!hasSelectedQuarter.value) {
        return null;
    }

    return buildQuarterResultFromEntries(simulationEntries.value, previousQuarterGe.value);
});

const displayedQuarterSummary = computed(() =>
    (simulationMode.value ? simulatedQuarterResult.value : null));

const finalAdjectival = computed(() =>
    selectedQuarterResult.value?.adjectival ?? getAdjectivalEquivalent(selectedQuarterResult.value?.finalGe));
</script>

<template>
    <StudentLayout>
        <Head title="Student Dashboard" />

        <div class="space-y-8">
            <!-- Info Card -->
            <div
                class="rounded-3xl p-8 shadow-sm border"
                :class="simulationMode
                    ? 'bg-emerald-50/50 border-emerald-100'
                    : 'bg-white border-slate-100'"
            >
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1">
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                            <p class="text-xs font-medium uppercase tracking-widest text-emerald-600">Current Section</p>
                        </div>

                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900">
                            {{ currentSectionLabel }}
                        </h2>
                        <p class="mt-1 text-lg text-slate-500">{{ currentYearLabel }}</p>

                        <p class="mt-6 text-sm text-slate-600">
                            Signed in as <span class="font-medium text-slate-700">{{ studentName }}</span>
                        </p>

                        <p v-if="!props.section" class="mt-6 text-sm text-rose-500">
                            We could not locate a section assignment for you. Please contact your adviser.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quarter Overview Card -->
            <div
                class="rounded-3xl p-8 shadow-sm border"
                :class="simulationMode
                    ? 'bg-emerald-50/50 border-emerald-100'
                    : 'bg-white border-slate-100'"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium uppercase tracking-[0.125em] text-slate-400">Quarterly Overview</p>
                    <button
                        type="button"
                        class="rounded-full border border-sky-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] transition shadow-sm bg-gradient-to-r from-sky-500 via-sky-600 to-cyan-500 text-white hover:from-sky-600 hover:via-sky-700 hover:to-cyan-600"
                        :class="simulationMode
                            ? 'ring-2 ring-sky-200 ring-offset-2 ring-offset-white'
                            : ''"
                        @click="simulationMode = !simulationMode"
                    >
                        {{ simulationMode ? 'Exit Simulation' : 'Simulation Mode' }}
                    </button>
                </div>

                <!-- Quarter Selector -->
                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        v-for="quarter in quarterOrder"
                        :key="quarter"
                        type="button"
                        class="rounded-2xl px-6 py-3 text-sm font-medium transition-all focus:outline-none"
                        :class="[
                            quarter === selectedQuarter
                                ? 'bg-emerald-600 text-white shadow-md'
                                : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                            !quarterAssessmentsByIndex[quarter]?.length && 'opacity-40 cursor-not-allowed'
                        ]"
                        :disabled="!quarterAssessmentsByIndex[quarter]?.length"
                        @click="selectedQuarter = quarter"
                    >
                        {{ quarterLabels[quarter] }}
                    </button>
                </div>

                <div v-if="!hasAssessments" class="mt-12 rounded-2xl bg-slate-50 py-16 text-center">
                    <p class="text-slate-400">No assessments have been recorded yet.</p>
                    <p class="mt-2 text-sm text-slate-500">Check back after your adviser records the assessment data.</p>
                </div>

                <!-- Selected Quarter Content -->
                <div v-else class="mt-10">
                    <div v-if="!hasSelectedQuarter" class="rounded-2xl bg-slate-50 py-12 text-center text-slate-500">
                        No data available for {{ quarterLabels[selectedQuarter] ?? 'this quarter' }} yet.
                    </div>

                    <div v-else class="space-y-8">
                        <!-- Detail Segments -->
                        <div v-if="hasSegmentEntries" class="grid gap-6 md:grid-cols-2">
                            <div
                                v-for="segment in detailSegments"
                                :key="segment"
                                class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm overflow-hidden"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                                    <p class="text-sm font-bold uppercase tracking-widest text-slate-500">
                                        {{ segmentConfig[segment].title }}
                                    </p>
                                </div>

                                <div v-if="detailEntriesForSegment(segment).length" class="mt-5 space-y-3">
                                    <div
                                        v-for="(item, entryIndex) in detailEntriesForSegment(segment)"
                                        :key="item.key"
                                        class="flex justify-between items-center rounded-2xl px-5 py-4 text-sm transition-colors"
                                        :class="item.tentative
                                            ? 'bg-amber-50 ring-1 ring-amber-200'
                                            : 'bg-slate-50'"
                                    >
                                        <span class="font-medium text-slate-700">
                                            {{ entryLabel(segment, item.label, entryIndex) }}
                                            <span
                                                v-if="item.tentative"
                                                class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700"
                                            >
                                                Tentative Score
                                            </span>
                                        </span>
                                        <div class="text-right">
                                            <input
                                                v-if="simulationMode && item.fieldType === 'score'"
                                                v-model="simulationDraft[item.key]"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-28 rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-sm font-semibold text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                            />
                                            <span v-else class="font-semibold text-slate-900">
                                                {{ formatAssessmentValue(item.label, displayEntryValue(segment, item)) }}
                                            </span>
                                            <span v-if="item.perfectScore" class="block text-xs text-slate-400 tracking-wider">
                                                / {{ formatAssessmentOverallValue(item.label, item.perfectScore) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mt-8 text-center text-xs uppercase tracking-widest text-slate-400">
                                    No entries yet
                                </p>
                            </div>
                        </div>

                        <!-- Live Simulation Breakdown -->
                        <div
                            v-if="simulationMode && displayedQuarterSummary"
                            class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-8 shadow-sm"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="uppercase tracking-[0.125em] text-xs text-emerald-600 font-medium">Simulation</p>
                                    <h4 class="text-2xl font-semibold text-slate-900 mt-1">Live Grade Breakdown</h4>
                                </div>
                                <span class="text-xs px-4 py-1.5 bg-white rounded-2xl text-emerald-600 font-medium border border-emerald-100">
                                    Editable
                                </span>
                            </div>

                            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                                <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-md ring-1 ring-emerald-100">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Current Quarter</p>
                                    <div class="mt-4">
                                        <div class="rounded-2xl bg-white/80 p-5 shadow-sm border border-emerald-100">
                                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Grade Equivalent</p>
                                            <p class="mt-2 text-4xl font-bold text-emerald-700">
                                                {{ formatPreciseDecimal(displayedQuarterSummary.currentGe) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Previous Quarter</p>
                                    <div class="mt-4">
                                        <div class="rounded-2xl bg-white p-5 shadow-sm border border-slate-200">
                                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Grade Equivalent</p>
                                            <p class="mt-2 text-4xl font-semibold text-slate-600">
                                                {{ formatPreciseDecimal(displayedQuarterSummary.previousGe) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-md ring-1 ring-amber-100">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Final Grade Equivalent</p>
                                    <p class="mt-2 text-4xl font-bold text-amber-700">
                                        {{ formatPreciseDecimal(displayedQuarterSummary.finalGe) }}
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-md ring-1 ring-amber-100">
                                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Adjectival Equivalent</p>
                                    <p class="mt-2 text-4xl font-bold text-amber-700">
                                        {{ displayedQuarterSummary.adjectival }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-3xl border border-sky-100 bg-white/90 p-5 hidden">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Calculation Breakdown</p>
                                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    <div
                                        v-for="segment in detailSegments"
                                        :key="`breakdown-${segment}`"
                                        class="rounded-2xl border border-sky-100 bg-sky-50/60 p-4"
                                    >
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-sky-700">
                                            {{ segmentConfig[segment].title }}
                                        </p>
                                        <div class="mt-3 space-y-2">
                                            <div
                                                v-for="item in detailEntriesForSegment(segment).filter((entry) => isScoreEntry(entry))"
                                                :key="`breakdown-${segment}-${item.key}`"
                                                class="flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm shadow-sm"
                                            >
                                                <span class="font-medium text-slate-700">{{ item.label }}</span>
                                                <span class="font-semibold text-slate-900">
                                                    {{ formatAssessmentValue(item.label, displayEntryValue(segment, item)) }}
                                                    <span v-if="item.perfectScore" class="text-slate-400">
                                                        / {{ formatAssessmentOverallValue(item.label, item.perfectScore) }}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-4 grid gap-2 text-sm">
                                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-2 shadow-sm">
                                                <span class="text-slate-600">Total</span>
                                                <span class="font-semibold text-slate-900">
                                                    {{ formatPreciseDecimal(displayedQuarterSummary.segments[segment].score, 2) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-2 shadow-sm">
                                                <span class="text-slate-600">Perfect</span>
                                                <span class="font-semibold text-slate-900">
                                                    {{ formatPreciseDecimal(displayedQuarterSummary.segments[segment].perfectScore, 2) }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-2 shadow-sm">
                                                <span class="text-slate-600">Percentage</span>
                                                <span class="font-semibold text-slate-900">
                                                    {{
                                                        displayedQuarterSummary.segments[segment].percentage === null
                                                            ? '—'
                                                            : `${displayedQuarterSummary.segments[segment].percentage}%`
                                                    }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between rounded-xl bg-white px-4 py-2 shadow-sm">
                                                <span class="text-slate-600">Weighted %</span>
                                                <span class="font-semibold text-slate-900">
                                                    {{
                                                        displayedQuarterSummary.segments[segment].weighted === null
                                                            ? '—'
                                                            : `${displayedQuarterSummary.segments[segment].weighted}%`
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">TW%</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ displayedQuarterSummary.twPercent === null ? '—' : `${displayedQuarterSummary.twPercent}%` }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">GE</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.currentGe) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">2/3</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.currentThird) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">G</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.previousGe) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">1/3</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.previousThird) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Trunc</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.trunc, 3) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Final GE</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ formatPreciseDecimal(displayedQuarterSummary.finalGe) }}
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-slate-500">Adjectival</p>
                                        <p class="mt-2 text-2xl font-bold text-slate-900">
                                            {{ displayedQuarterSummary.adjectival }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Final Grade Overview -->
                        <div
                            v-else-if="selectedQuarterResult"
                            class="rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-8 shadow-sm"
                        >
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="uppercase tracking-[0.125em] text-xs text-indigo-600 font-medium">Summary</p>
                                    <h4 class="text-2xl font-semibold text-slate-900 mt-1">Final Grade Overview</h4>
                                </div>
                                <span class="text-xs px-4 py-1.5 bg-white rounded-2xl text-indigo-600 font-medium border border-indigo-100">
                                    Weighted
                                </span>
                            </div>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2 md:grid-cols-2">
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">Grade Equivalent</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ formatTwoDecimals(selectedQuarterResult.finalGe) }}
                                    </p>
                                </div>
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">Adjectival Rating</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ finalAdjectival }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="selectedQuarterResult.hasTentative"
                                class="mt-4 text-xs font-semibold uppercase tracking-widest text-amber-600"
                            >
                                Includes tentative scores
                            </p>
                        </div>

                        <p v-else class="text-center text-slate-500 py-8">
                            This quarter does not contain any assessment data yet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
