<script setup>
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import StudentLayout from '@/Layouts/StudentLayout.vue';

const props = defineProps({
    student: {
        type: Object,
        default: null,
    },
    section: {
        type: Object,
        default: null,
    },
    schoolYear: {
        type: Object,
        default: null,
    },
    quarterlyAssessments: {
        type: Array,
        default: () => [],
    },
});

const studentName = computed(() => {
    if (!props.student) {
        return 'Scholar';
    }

    const parts = [
        props.student.last_name,
        props.student.first_name,
    ]
        .filter(Boolean)
        .map((part) => part.trim());

    if (parts.length) {
        return parts.join(', ');
    }

    return props.student.email ?? 'Scholar';
});

const currentSectionLabel = computed(() => {
    if (!props.section?.section_name) {
        return 'Section not assigned';
    }

    return props.section.grade_level
        ? `${props.section.grade_level} · ${props.section.section_name}`
        : props.section.section_name;
});

const currentYearLabel = computed(() => {
    if (!props.schoolYear?.year_start) {
        return 'School year not available';
    }

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
    const mapping = quarterOrder.reduce((acc, quarter) => {
        acc[quarter] = [];
        return acc;
    }, {});

    props.quarterlyAssessments.forEach((assessment) => {
        const quarterIndex = Number(
            assessment.quarter?.index ?? assessment.quarter?.quarter ?? null
        );

        if (!quarterOrder.includes(quarterIndex)) {
            return;
        }

        mapping[quarterIndex].push(assessment);
    });

    return Object.fromEntries(
        Object.entries(mapping).map(([quarter, assessments]) => [
            quarter,
            assessments
                .slice()
                .sort((a, b) => new Date(b.uploadedAt ?? b.created_at) - new Date(a.uploadedAt ?? a.created_at)),
        ])
    );
});

const selectedQuarter = ref(null);
const selectedQuarterAssessment = computed(() => {
    if (!selectedQuarter.value) {
        return null;
    }

    return (
        (quarterAssessmentsByIndex.value[selectedQuarter.value] ?? [])[0] ?? null
    );
});

const defaultQuarterWithData = computed(() =>
    quarterOrder.find((quarter) => quarterAssessmentsByIndex.value[quarter]?.length)
);

watch(
    () => defaultQuarterWithData.value,
    (value) => {
        if (!value) {
            selectedQuarter.value = null;
            return;
        }

        if (
            !selectedQuarter.value ||
            !quarterAssessmentsByIndex.value[selectedQuarter.value]?.length
        ) {
            selectedQuarter.value = value;
        }
    },
    { immediate: true }
);

const quarterTitle = (assessment) => {
    if (assessment.quarter?.name) {
        return assessment.quarter.name;
    }

    if (assessment.quarter?.index) {
        return `Quarter ${assessment.quarter.index}`;
    }

    return 'Quarter';
};

const getColumns = (assessment) => {
    const headers = assessment.studentRow?.headers ?? [];

    if (headers.length) {
        return headers;
    }

    return (assessment.studentRow?.values ?? []).map((_, index) => `Column ${index + 1}`);
};

const formatUploadedAt = (value) => {
    if (!value) {
        return 'Date unavailable';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const yearLabelForAssessment = (assessment) => {
    if (assessment.schoolYear?.year_start) {
        return `${assessment.schoolYear.year_start}-${assessment.schoolYear.year_end}`;
    }

    return 'School year not set';
};

const sectionLabelForAssessment = (assessment) => {
    const section = assessment.section;

    if (!section?.section_name) {
        return 'Section not assigned';
    }

    return section.grade_level
        ? `${section.grade_level} · ${section.section_name}`
        : section.section_name;
};

const normalizeHeaderLabel = (header) => {
    const trimmed = header?.trim() ?? '';
    if (!trimmed) {
        return '';
    }
    if (/^t$/i.test(trimmed)) {
        return 'Total';
    }
    if (/^%$/i.test(trimmed)) {
        return 'Percentage';
    }
    if (/^w%$/i.test(trimmed) || /^w\s?%$/i.test(trimmed) || /^weighted\s?%$/i.test(trimmed)) {
        return 'Weighted %';
    }
    return trimmed.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
};

const formatLabel = (label) => {
    const normalized = label.replace(/_/g, ' ').trim();
    return normalized
        ? normalized
              .split(' ')
              .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
              .join(' ')
        : label;
};

const summaryLabels = (assessment) => {
    const rows = assessment?.studentRow?.subheaders ?? [];

    return rows
        .map((row) =>
            row
                .map((cell) => (cell ? String(cell).trim() : ''))
                .filter(Boolean)
                .join(' · ')
        )
        .filter(Boolean);
};

const detectSegment = (normalized) => {
    if (!normalized) {
        return null;
    }

    if (/(lt1|long test 1|longtest1|lt 1)/.test(normalized)) {
        return 'lt1';
    }

    if (/(lt2|long test 2|longtest2|lt 2)/.test(normalized)) {
        return 'lt2';
    }

    if (/(aa|alternative)/.test(normalized)) {
        return 'aa';
    }

    if (/(fa|formative)/.test(normalized)) {
        return 'fa';
    }

    if (/(tw|two.*third|adjectival|final|trunc|gscore?|ge|one.*third)/.test(normalized)) {
        return 'final';
    }

    return null;
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

const extractSegments = (assessment) => {
    const headers = assessment.studentRow?.headers ?? [];
    const values = assessment.studentRow?.values ?? [];
    const subheaderValues = assessment.studentRow?.subheader_values ?? [];
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
        const normalized = header?.toLowerCase() ?? '';
        const detected = detectSegment(normalized);
        if (detected) {
            currentSegment = detected;
        }

        if (/(family|given|middle|gender)/.test(normalized)) {
            return;
        }

        const bucket = currentSegment ?? 'other';
        const normalizedLabel = normalizeHeaderLabel(header || `Column ${index + 1}`);
        const isWeighted = /weighted\s?%$/i.test(normalizedLabel);
        const perfectScore = sanitizePerfectScore(subheaderValues[index]);
        const entry = {
            label: normalizedLabel,
            value: values[index] || '—',
            perfectScore: perfectScore ?? (isWeighted ? '25' : null),
        };

        buckets[bucket].push(entry);
    });

    return buckets;
};

const entriesForSegment = (assessment, segment) => {
    const segments = extractSegments(assessment);
    return segments[segment] ?? [];
};

const finalSegmentEntries = (assessment) => entriesForSegment(assessment, 'final');

const finalGradeOverview = (assessment) => {
    const entries = finalSegmentEntries(assessment);
    const geEntries = entries.filter((entry) => /^ge$/i.test(entry.label) || /ge\s?[^a-z]*$/i.test(entry.label));
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
    if (!assessment) {
        return false;
    }

    const overview = finalGradeOverview(assessment);
    return Boolean(overview.ge || overview.adjectival);
};

const segmentConfig = {
    lt1: { title: 'Long Test 1', accent: 'from-emerald-100 via-emerald-50 to-white' },
    lt2: { title: 'Long Test 2', accent: 'from-sky-100 via-slate-50 to-white' },
    aa: { title: 'Alternative Assessments', accent: 'from-yellow-100 via-amber-50 to-white' },
    fa: { title: 'Formative Assessments', accent: 'from-slate-100 via-slate-50 to-white' },
    final: { title: 'Quarterly summary', accent: 'from-indigo-100 via-indigo-50 to-white' },
};

const detailSegments = ['lt1', 'lt2', 'aa', 'fa'];
</script>

<template>
    <StudentLayout>
        <Head title="Student dashboard" />

        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Your section</p>
                        <h2 class="text-2xl font-semibold text-slate-900">{{ currentSectionLabel }}</h2>
                        <p class="text-sm text-slate-500">{{ currentYearLabel }}</p>
                        <p class="text-xs text-slate-400">Signed in as {{ studentName }}</p>
                        <p v-if="!props.section" class="mt-4 text-sm text-rose-500">
                            We could not locate a section assignment for you. Contact your adviser so it can be linked to
                            your record.
                        </p>
                    </div>
                    <div class="text-sm text-slate-500">
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Quarterly data</p>
                        <p class="text-lg font-semibold text-slate-900">{{ hasAssessments ? props.quarterlyAssessments.length : '0' }}</p>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Uploads</p>
                    </div>
                </div>
            </div>

              <div class="rounded-3xl bg-white p-6 shadow-lg">
                  <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Quarter overview</p>
                  <div class="mt-4 flex flex-wrap items-center gap-3">
                      <button
                          v-for="quarter in quarterOrder"
                          :key="quarter"
                          type="button"
                          class="rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.35em] transition focus:outline-none"
                          :class="[
                              quarter === selectedQuarter ? 'border-emerald-500 bg-emerald-50 text-emerald-600' : 'border-slate-200 text-slate-600',
                              !quarterAssessmentsByIndex[quarter]?.length ? 'cursor-not-allowed opacity-50' : 'hover:border-slate-300 hover:bg-slate-50'
                          ]"
                          :disabled="!quarterAssessmentsByIndex[quarter]?.length"
                          @click="selectedQuarter = quarter"
                      >
                          {{ quarterLabels[quarter] }}
                      </button>
                  </div>

                  <div v-if="!hasAssessments" class="mt-6 rounded-3xl bg-white text-center text-sm text-slate-500 shadow-inner">
                      No quarterly breakdowns have been uploaded for this section yet. Check back once your adviser saves the
                      CSV file.
                  </div>

                  <div v-else class="mt-6 space-y-4">
                      <div
                          v-if="!selectedQuarterAssessment"
                          class="rounded-3xl bg-white text-center text-sm text-slate-500 shadow-inner"
                      >
                          No uploads yet for {{ quarterLabels[selectedQuarter] ?? 'this quarter' }}.
                      </div>

                      <div v-else class="space-y-4 rounded-3xl bg-white shadow-lg">
                          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                              <div>
                                  <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Quarterly upload</p>
                                  <h3 class="text-lg font-semibold text-slate-900">{{ quarterTitle(selectedQuarterAssessment) }}</h3>
                                  <p class="text-sm text-slate-500">{{ sectionLabelForAssessment(selectedQuarterAssessment) }}</p>
                              </div>
                              <div class="text-xs text-slate-500">
                                  <p>{{ yearLabelForAssessment(selectedQuarterAssessment) }}</p>
                                  <p>Uploaded by {{ selectedQuarterAssessment.uploadedBy ?? 'Unknown' }}</p>
                                  <p>{{ formatUploadedAt(selectedQuarterAssessment.uploadedAt) }}</p>
                              </div>
                          </div>

                          <div v-if="selectedQuarterAssessment.studentRow?.values?.length" class="grid gap-4 md:grid-cols-2">
                              <div
                                  v-for="segment in detailSegments"
                                  :key="segment"
                                  class="border border-slate-100 rounded-2xl p-4 shadow-sm"
                                  :class="`bg-gradient-to-br ${segmentConfig[segment].accent}`"
                              >
                                  <p class="text-[0.6rem] font-semibold uppercase tracking-[0.4em] text-slate-500">
                                      {{ segmentConfig[segment].title }}
                                  </p>

                                  <div v-if="entriesForSegment(selectedQuarterAssessment, segment).length" class="mt-3 space-y-2">
                            <div
                                v-for="item in entriesForSegment(selectedQuarterAssessment, segment)"
                                :key="`${selectedQuarterAssessment.id}-${segment}-${item.label}`"
                                class="flex items-center justify-between rounded-xl bg-white/70 px-3 py-2 text-sm text-slate-700 shadow-inner"
                            >
                                <span class="font-semibold text-slate-600">{{ formatLabel(item.label) }}</span>
                                <div class="text-right">
                                    <span class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">
                                        {{ item.value }}
                                    </span>
                                    <span
                                        v-if="item.perfectScore"
                                        class="block text-[0.6rem] uppercase tracking-[0.35em] text-slate-400"
                                    >
                                        / {{ item.perfectScore }}
                                    </span>
                                </div>
                            </div>
                                  </div>
                                  <p
                                      v-else
                                      class="mt-3 text-xs font-semibold uppercase tracking-[0.4em] text-slate-400"
                                  >
                                      No data for this section yet.
                                  </p>
                              </div>
                          </div>

                          <div
                              v-if="hasFinalOverview(selectedQuarterAssessment)"
                              class="rounded-3xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-white p-6 shadow-xl"
                          >
                              <div class="flex items-center justify-between">
                                  <div>
                                      <p class="text-xs uppercase tracking-[0.4em] text-indigo-500">Quarterly summary</p>
                                      <h4 class="text-xl font-semibold text-slate-900">Final grade overview</h4>
                                  </div>
                                  <span class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-600">Weighted focus</span>
                              </div>

                              <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div
                            v-if="finalGradeOverview(selectedQuarterAssessment).trunc"
                            class="flex items-center justify-between rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-800 shadow-inner"
                        >
                            <span class="font-bold uppercase tracking-[0.35em] text-indigo-600">{{ formatLabel(finalGradeOverview(selectedQuarterAssessment).trunc.label) }}</span>
                            <span class="text-base font-semibold text-slate-900">{{ finalGradeOverview(selectedQuarterAssessment).trunc.value }}</span>
                        </div>
                        <div
                            v-if="finalGradeOverview(selectedQuarterAssessment).ge"
                            class="flex items-center justify-between rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-800 shadow-inner"
                        >
                            <span class="font-bold uppercase tracking-[0.35em] text-indigo-600">{{ formatLabel(finalGradeOverview(selectedQuarterAssessment).ge.label) }}</span>
                            <span class="text-base font-semibold text-slate-900">{{ finalGradeOverview(selectedQuarterAssessment).ge.value }}</span>
                        </div>
                                  <div
                                      v-if="finalGradeOverview(selectedQuarterAssessment).adjectival"
                                      class="flex items-center justify-between rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-800 shadow-inner"
                                  >
                                      <span class="font-bold uppercase tracking-[0.35em] text-indigo-600">{{ formatLabel(finalGradeOverview(selectedQuarterAssessment).adjectival.label) }}</span>
                                      <span class="text-base font-semibold text-slate-900">{{ finalGradeOverview(selectedQuarterAssessment).adjectival.value }}</span>
                                  </div>
                              </div>
                          </div>

                          <p v-else-if="selectedQuarterAssessment.hasPayload" class="text-sm text-slate-500">
                              We could not locate your row in this upload. Double-check that your name and email match the CSV file so the
                              correct record can be shown.
                          </p>

                          <p v-else class="text-sm text-slate-500">
                              This upload does not contain any data rows yet.
                          </p>
                      </div>
                  </div>
              </div>
          </div>
    </StudentLayout>
</template>
