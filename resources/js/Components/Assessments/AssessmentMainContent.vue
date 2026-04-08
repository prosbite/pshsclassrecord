<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatDate } from '@/Composables/utilities.js';

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
    sectionFilter: {
        type: String,
        default: 'all',
    },
});

const selectedSection = ref(props.sectionFilter ?? 'all');

const sectionOptions = computed(() => {
    const options = props.sections.map((section) => {
        const grade = section.grade_level?.grade_level;
        const name = section.section_name || 'Section';
        const label = grade ? `${grade} · ${name}` : name;
        return {
            id: String(section.id),
            label,
        };
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
    { immediate: true }
);

watch(
    () => props.sectionFilter,
    (value) => {
        const normalized = value ?? 'all';

        if (normalized !== selectedSection.value) {
            selectedSection.value = normalized;
        }
    }
);

watch(selectedSection, (value) => {
    const normalized = value || 'all';
    const currentFilter = props.sectionFilter ?? 'all';

    if (normalized === currentFilter) {
        return;
    }

    const query = normalized === 'all' ? {} : { section: normalized };

    router.visit(route('assessments.index'), {
        method: 'get',
        data: query,
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const filteredAssessments = computed(() => {
    const selection = selectedSection.value;

    if (!selection || selection === 'all') {
        return props.assessments;
    }

    if (selection === 'unassigned') {
        return props.assessments.filter((assessment) => !assessment.section?.id);
    }

    return props.assessments.filter((assessment) => String(assessment.section?.id) === selection);
});

const totalAssessments = computed(() => filteredAssessments.value.length);

const activeYearLabel = computed(() => {
    if (!props.schoolYear) {
        return 'No active school year yet';
    }

    if (props.schoolYear.year_start && props.schoolYear.year_end) {
        return `${props.schoolYear.year_start}-${props.schoolYear.year_end}`;
    }

    return 'Active school year';
});

const quarterGroups = computed(() => {
    const entries = [...filteredAssessments.value];
    entries.sort((a, b) => {
        const aQuarter = a.quarter?.quarter ?? 0;
        const bQuarter = b.quarter?.quarter ?? 0;

        if (aQuarter !== bQuarter) {
            return aQuarter - bQuarter;
        }

        const aCreated = a.created_at ? new Date(a.created_at).getTime() : 0;
        const bCreated = b.created_at ? new Date(b.created_at).getTime() : 0;

        return bCreated - aCreated;
    });

    const groups = new Map();

    entries.forEach((assessment) => {
        const quarterNumber = assessment.quarter?.quarter ?? null;
        const key = quarterNumber !== null ? `quarter-${quarterNumber}` : 'quarter-unassigned';

        if (!groups.has(key)) {
            const quarterLabel = quarterNumber !== null ? `Quarter ${quarterNumber}` : 'Unassigned quarter';
            const startDate = assessment.quarter?.start_date;
            const endDate = assessment.quarter?.end_date;
            const range =
                startDate && endDate
                    ? `${formatDate(startDate)} — ${formatDate(endDate)}`
                    : 'Dates pending';

            groups.set(key, {
                key,
                label: quarterLabel,
                range,
                items: [],
            });
        }

        groups.get(key).items.push(assessment);
    });

    return Array.from(groups.values());
});

const hasAssessments = computed(() => totalAssessments.value > 0);
const createAssessmentUrl = computed(() => route('assessments.create'));
const summaryUrl = computed(() => {
    const normalized = selectedSection.value || 'all';
    const query = normalized === 'all' ? {} : { section: normalized };

    return route('assessments.summary', query);
});
const goToAssessment = (assessment) => {
    router.visit(route('assessments.show', assessment.id));
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2 rounded-3xl bg-white p-6 shadow-lg sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Assessments</p>
                <h1 class="text-2xl font-semibold text-slate-900">Quarterly assessments</h1>
                <p class="text-sm text-slate-500">
                    Review all assessments recorded for the current school year.
                </p>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-3 sm:mt-0">
                <div class="flex items-center gap-2">
                    <label for="section-filter" class="sr-only">Filter assessments by section</label>
                    <select
                        id="section-filter"
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
                <Link
                    :href="summaryUrl"
                    class="inline-flex items-center rounded-full bg-sky-600 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-white"
                >
                    View summary
                </Link>
                <Link
                    :href="createAssessmentUrl"
                    class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
                    Create assessment
                </Link>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">School year</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ activeYearLabel }}</p>
                <p class="text-sm text-slate-500">
                    Current school year with {{ totalAssessments }} {{ totalAssessments === 1 ? 'assessment' : 'assessments' }} tracked.
                </p>
            </div>

            <div class="rounded-3xl bg-gradient-to-br from-sky-500 to-blue-600 p-6 text-white shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em]">Assessments</p>
                <p class="mt-2 text-3xl font-semibold">{{ totalAssessments }}</p>
                <p class="text-sm text-white/80">
                    {{ hasAssessments ? 'Organized by quarter below' : 'Add your first assessment to get started' }}.
                </p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Activity</p>
                <p class="mt-3 text-sm text-slate-500">
                    {{ hasAssessments ? quarterGroups.length : 'No quarterly activity yet' }}
                    {{ hasAssessments ? 'quarters covered' : '' }}.
                </p>
                <p v-if="hasAssessments" class="mt-2 text-sm font-medium text-slate-700">
                    First quarter: {{ quarterGroups[0]?.label ?? '—' }}
                </p>
            </div>
        </div>

        <div class="space-y-4">
            <div
                v-if="!hasAssessments"
                class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm"
            >
                No assessments have been recorded for this school year yet.
            </div>

            <div
                v-for="group in quarterGroups"
                :key="group.key"
                class="overflow-hidden rounded-3xl bg-white shadow-lg"
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 sm:px-8">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">{{ group.label }}</p>
                        <p class="text-sm text-slate-500">{{ group.range }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase text-slate-600">
                        {{ group.items.length }} {{ group.items.length === 1 ? 'assessment' : 'assessments' }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Title</th>
                                <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 font-semibold">Section</th>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Teacher</th>
                            <th class="px-6 py-3 font-semibold text-right">Learners</th>
                            <th class="px-6 py-3 font-semibold text-right">Recorded</th>
                        </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="assessment in group.items"
                                :key="assessment.id"
                                class="border-b last:border-b-0 odd:bg-white even:bg-slate-50 cursor-pointer transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                                @click="goToAssessment(assessment)"
                                role="button"
                                tabindex="0"
                            >
                                <td class="px-6 py-4 font-semibold text-slate-900">
                                    <div>{{ assessment.title || assessment.assessmentType?.name || 'Assessment' }}</div>
                                    <span class="text-xs text-slate-500">
                                        {{ assessment.section?.section_name || 'General' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <span>{{ assessment.assessmentType?.name || '—' }}</span>
                                        <span
                                            v-if="assessment.assessmentType?.percentage"
                                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"
                                        >
                                            {{ assessment.assessmentType?.percentage }}%
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-400">
                                        {{ assessment.section?.grade_level?.grade_level || 'Unassigned grade' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ assessment.section?.section_name || '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ formatDate(assessment.assessment_date) || '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ assessment.user?.name || 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">
                                    {{ assessment.learners_count ?? 0 }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-slate-500">
                                    {{ formatDate(assessment.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
