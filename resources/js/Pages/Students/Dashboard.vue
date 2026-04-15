<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    student: { type: Object, default: null },
    section: { type: Object, default: null },
    schoolYear: { type: Object, default: null },
    quarterlyAssessments: { type: Array, default: () => [] },
});

// ... (All your existing computed properties, methods, and logic remain unchanged)
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

const hasAssessments = computed(() => props.quarterlyAssessments.length > 0);

const quarterOrder = [1, 2, 3, 4];
const quarterLabels = {
    1: '1st Quarter',
    2: '2nd Quarter',
    3: '3rd Quarter',
    4: '4th Quarter',
};

const quarterAssessmentsByIndex = computed(() => {
    const mapping = quarterOrder.reduce((acc, quarter) => { acc[quarter] = []; return acc; }, {});

    props.quarterlyAssessments.forEach((assessment) => {
        const quarterIndex = Number(assessment.quarter?.index ?? assessment.quarter?.quarter ?? null);
        if (!quarterOrder.includes(quarterIndex)) return;

        mapping[quarterIndex].push(assessment);
    });

    return Object.fromEntries(
        Object.entries(mapping).map(([quarter, assessments]) => [
            quarter,
            assessments.slice().sort((a, b) => new Date(b.uploadedAt ?? b.created_at) - new Date(a.uploadedAt ?? a.created_at))
        ])
    );
});

const selectedQuarter = ref(4);
const selectedQuarterAssessment = computed(() => {
    if (!selectedQuarter.value) return null;
    return (quarterAssessmentsByIndex.value[selectedQuarter.value] ?? [])[0] ?? null;
});

const isFourthQuarterAssessment = (assessment) => {
    const quarterIndex = Number(assessment?.quarter?.index ?? assessment?.quarter?.quarter ?? null);
    return quarterIndex === 4;
};

// ... (Keep all your other methods unchanged: quarterTitle, getColumns, formatUploadedAt, etc.)
const quarterTitle = (assessment) => assessment.quarter?.name || (assessment.quarter?.index ? `Quarter ${assessment.quarter.index}` : 'Quarter');

const yearLabelForAssessment = (assessment) =>
    assessment.schoolYear?.year_start
        ? `${assessment.schoolYear.year_start}-${assessment.schoolYear.year_end}`
        : 'School year not set';

const sectionLabelForAssessment = (assessment) => {
    const section = assessment.section;
    if (!section?.section_name) return 'Section not assigned';
    return section.grade_level
        ? `${section.grade_level} · ${section.section_name}`
        : section.section_name;
};

const formatUploadedAt = (value) =>
    value
        ? new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : 'Date unavailable';

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

const normalizeTentativeLabel = (label) => {
    return (label ?? '').toString().toLowerCase().replace(/\s+/g, '');
};

const isTentativeScore = (assessment, segment, item, entryIndex) => {
    if (!isFourthQuarterAssessment(assessment)) {
        return false;
    }

    const normalizedLabel = normalizeTentativeLabel(item?.label);

    if (segment === 'lt2' && entryIndex === 0) {
        return true;
    }

    if (segment === 'aa' && /^aa2\b/.test(normalizedLabel)) {
        return true;
    }

    if (segment === 'fa' && /^fa3\b/.test(normalizedLabel)) {
        return true;
    }

    return false;
};

const normalizeHeaderLabel = (header) => {
    const trimmed = (header ?? '').trim();
    if (!trimmed) return '';
    if (/^t$/i.test(trimmed)) return 'Total';
    if (/^%$/i.test(trimmed)) return 'Percentage';
    if (/^w%$/i.test(trimmed) || /^w\s?%$/i.test(trimmed) || /^weighted\s?%$/i.test(trimmed)) {
        return 'Weighted %';
    }
    return trimmed.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
};

const sanitizePerfectScore = (value) => {
    if (value === null || value === undefined) {
        return null;
    }

    const candidate = String(value).trim();
    if (!candidate || candidate === '—' || candidate.toUpperCase() === '#REF!') {
        return null;
    }

    return candidate;
};

const detectSegment = (normalized) => {
    if (!normalized) return null;
    if (/(lt1|long\s?test\s?1|longtest1)/i.test(normalized)) return 'lt1';
    if (/(lt2|long\s?test\s?2|longtest2)/i.test(normalized)) return 'lt2';
    if (/(aa|alternative)/i.test(normalized)) return 'aa';
    if (/(fa|formative)/i.test(normalized)) return 'fa';
    if (/(final|trunc|ge|adjectival|two.*third|one.*third)/i.test(normalized)) return 'final';
    return null;
};

const extractSegments = (assessment) => {
    const headers = assessment?.studentRow?.headers ?? [];
    const values = assessment?.studentRow?.values ?? [];
    const subheaders = assessment?.studentRow?.subheader_values ?? [];

    const buckets = {
        lt1: [],
        lt2: [],
        aa: [],
        fa: [],
        final: [],
        other: [],
    };

    let currentSegment = null;

    headers.forEach((header, index) => {
        const normalized = (header ?? '').toLowerCase();
        const detected = detectSegment(normalized);

        if (detected) {
            currentSegment = detected;
        }

        if (/(family|given|middle|gender)/i.test(normalized)) {
            return;
        }

        const entry = {
            label: normalizeHeaderLabel(header || `Column ${index + 1}`),
            value: values[index] ?? '—',
            perfectScore: sanitizePerfectScore(subheaders[index]),
        };

        const bucket = currentSegment ?? 'other';
        buckets[bucket] = buckets[bucket] ?? [];
        buckets[bucket].push(entry);
    });

    return buckets;
};

const entriesForSegment = (assessment, segment) => {
    if (!assessment) return [];
    return extractSegments(assessment)[segment] ?? [];
};

const isTwoThirdEntry = (label) => {
    const normalized = (label ?? '').toLowerCase();
    return /(tw%?|two.*third)/i.test(normalized);
};

const detailEntriesForSegment = (assessment, segment) => {
    const entries = entriesForSegment(assessment, segment);
    if (segment !== 'fa') {
        return entries;
    }

    return entries.filter((entry) => !isTwoThirdEntry(entry.label));
};

const finalSegmentEntries = (assessment) => entriesForSegment(assessment, 'final');

const finalGradeOverview = (assessment) => {
    const entries = finalSegmentEntries(assessment);
    const geEntries = entries.filter(
        (entry) => /^ge$/i.test(entry.label) || /ge\s?[^a-z]*$/i.test(entry.label)
    );
    const adjectivalEntry = entries.find((entry) => /adjectival/i.test(entry.label));
    const truncEntry = entries.find((entry) => /trunc/i.test(entry.label));
    const geEntry = geEntries.length ? geEntries[geEntries.length - 1] : null;

    return {
        ge: geEntry,
        adjectival: adjectivalEntry,
        trunc: truncEntry,
    };
};

const hasFinalOverview = (assessment) => {
    if (!assessment) return false;
    const overview = finalGradeOverview(assessment);
    return Boolean(overview.ge || overview.adjectival || overview.trunc);
};

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

const twoThirdEntry = (assessment) => {
    if (!assessment) return null;
    return entriesForSegment(assessment, 'fa').find((entry) => isTwoThirdEntry(entry.label)) ?? null;
};
</script>

<template>
    <StudentLayout>
        <Head title="Student Dashboard" />

        <div class="space-y-8">
            <!-- Info Card -->
            <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
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

                    <!-- <div class="flex-shrink-0 text-right">
                        <p class="text-xs uppercase tracking-[0.125em] text-slate-400">Quarterly Uploads</p>
                        <p class="mt-1 text-5xl font-semibold tracking-tighter text-slate-900">
                            {{ hasAssessments ? props.quarterlyAssessments.length : 0 }}
                        </p>
                        <p class="text-sm text-slate-500">assessments</p>
                    </div> -->
                </div>
            </div>

            <!-- Quarter Overview Card -->
            <div class="rounded-3xl bg-white p-8 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium uppercase tracking-[0.125em] text-slate-400">Quarterly Overview</p>
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
                    <p class="text-slate-400">No quarterly breakdowns uploaded yet.</p>
                    <p class="mt-2 text-sm text-slate-500">Check back after your adviser uploads the assessment data.</p>
                </div>

                <!-- Selected Quarter Content -->
                <div v-else class="mt-10">
                    <div v-if="!selectedQuarterAssessment" class="rounded-2xl bg-slate-50 py-12 text-center text-slate-500">
                        No data available for {{ quarterLabels[selectedQuarter] ?? 'this quarter' }} yet.
                    </div>

                    <div v-else class="space-y-8">
                        <!-- Upload Info -->

                        <!-- Detail Segments -->
                        <div v-if="selectedQuarterAssessment.studentRow?.values?.length"
                             class="grid gap-6 md:grid-cols-2">
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

                                <div v-if="detailEntriesForSegment(selectedQuarterAssessment, segment).length"
                                     class="mt-5 space-y-3">
                                    <div
                                        v-for="(item, entryIndex) in detailEntriesForSegment(selectedQuarterAssessment, segment)"
                                        :key="`${selectedQuarterAssessment.id}-${segment}-${item.label}`"
                                        :class="[
                                            'flex justify-between items-center rounded-2xl px-5 py-4 text-sm transition-colors',
                                            isTentativeScore(selectedQuarterAssessment, segment, item, entryIndex)
                                                ? 'border border-amber-300 bg-amber-50/80'
                                                : 'bg-slate-50',
                                        ]"
                                    >
                                        <span class="font-medium text-slate-700">{{ entryLabel(segment, item.label, entryIndex) }}</span>
                                        <div class="text-right">
                                            <span
                                                v-if="isTentativeScore(selectedQuarterAssessment, segment, item, entryIndex)"
                                                class="mb-1 inline-flex rounded-full bg-amber-200 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-amber-800"
                                            >
                                                Tentative Score
                                            </span>
                                            <span class="font-semibold text-slate-900">{{ formatAssessmentValue(item.label, item.value) }}</span>
                                            <span v-if="item.perfectScore"
                                                  class="block text-xs text-slate-400 tracking-wider">
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

                        <!-- Final Grade Overview -->
                        <div v-if="hasFinalOverview(selectedQuarterAssessment)"
                             class="rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-8 shadow-sm">
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
                                <!-- <div v-if="twoThirdEntry(selectedQuarterAssessment)"
                                     class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">TW%</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ twoThirdEntry(selectedQuarterAssessment).value }}
                                    </p>
                                    <span v-if="twoThirdEntry(selectedQuarterAssessment).perfectScore"
                                          class="block text-xs text-slate-400 tracking-wider mt-1">
                                        / {{ twoThirdEntry(selectedQuarterAssessment).perfectScore }}
                                    </span>
                                </div>
                                <div v-if="finalGradeOverview(selectedQuarterAssessment).trunc"
                                     class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">Truncated / Final</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ finalGradeOverview(selectedQuarterAssessment).trunc.value }}
                                    </p>
                                </div> -->
                                <div v-if="finalGradeOverview(selectedQuarterAssessment).ge"
                                     class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">Grade Equivalent</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ formatTwoDecimals(finalGradeOverview(selectedQuarterAssessment).ge.value) }}
                                    </p>
                                </div>
                                <div v-if="finalGradeOverview(selectedQuarterAssessment).adjectival"
                                     class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                                    <p class="text-xs text-slate-500">Adjectival Rating</p>
                                    <p class="text-3xl font-semibold text-slate-900 mt-2">
                                        {{ formatTwoDecimals(finalGradeOverview(selectedQuarterAssessment).adjectival.value) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Fallback Messages -->
                        <p v-else-if="selectedQuarterAssessment.hasPayload"
                           class="text-center text-slate-500 py-8">
                            We couldn't find your record in this upload. Please ensure your name/email matches the CSV.
                        </p>
                        <p v-else class="text-center text-slate-500 py-8">
                            This assessment does not contain any student data rows yet.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </StudentLayout>
</template>
