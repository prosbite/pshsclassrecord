<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    quarterlyAssessments: {
        type: Array,
        default: () => [],
    },
    schoolYear: {
        type: Object,
        default: null,
    },
});

const formatTimestamp = (value) => {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const formatSection = (assessment) => {
    const section = assessment.section;
    if (!section) {
        return 'Unassigned section';
    }

    const grade = section.grade_level?.grade_level;
    const name = section.section_name || 'General';

    return grade ? `${grade} · ${name}` : name;
};

const formatQuarter = (assessment) => {
    const quarter = assessment.quarter;
    if (!quarter) {
        return '—';
    }

    return quarter.name || `Quarter ${quarter.quarter ?? '—'}`;
};

const formatSchoolYear = (assessment) => {
    const schoolYear = assessment.schoolYear;
    if (!schoolYear) {
        return '—';
    }

    return `${schoolYear.year_start}-${schoolYear.year_end}`;
};

const getAssessmentRows = (assessment) => {
    const payload = assessment.assessment;

    if (!payload) {
        return [];
    }

    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload.rows)) {
        return payload.rows;
    }

    return [];
};

const getAssessmentHeaders = (assessment) => {
    const payload = assessment.assessment;

    if (!payload || Array.isArray(payload)) {
        return [];
    }

    return Array.isArray(payload.headers) ? payload.headers : [];
};

const columnPreview = (assessment) => {
    const headers = getAssessmentHeaders(assessment);
    const rows = getAssessmentRows(assessment);
    if (!rows.length) {
        return 'No data';
    }

    if (headers.length) {
        return headers.join(', ');
    }

    const firstRow = rows[0];

    if (Array.isArray(firstRow)) {
        return firstRow.map((_, index) => `Column ${index + 1}`).join(', ');
    }

    const columns = Object.keys(firstRow);
    return columns.length ? columns.join(', ') : 'No columns';
};

const rowCount = computed(() => props.quarterlyAssessments.length);

const assessmentsWithDerived = computed(() =>
    props.quarterlyAssessments.map((assessment) => ({
        ...assessment,
        rows: getAssessmentRows(assessment).length,
        columns: columnPreview(assessment),
    })),
);

const handleRowClick = (assessment) => {
    router.visit(route('quarterly-assessments.show', assessment.id));
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Quarterly assessments</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Saved quarterly assessments</h1>
                        <p class="text-sm text-slate-500">
                            View every uploaded quarterly breakdown for {{ schoolYear ? `${schoolYear.year_start}-${schoolYear.year_end}` : 'the active school year' }}.
                        </p>
                    </div>
                    <Link
                        :href="route('quarterly-assessments.upload')"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Upload new CSV
                    </Link>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Saved records</p>
                        <h2 class="text-lg font-semibold text-slate-900">{{ rowCount }} saved quarterly assessments</h2>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-600">
                        <thead class="text-[0.65rem] uppercase tracking-[0.35em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Section</th>
                                <th class="px-4 py-3">Quarter</th>
                                <th class="px-4 py-3">School Year</th>
                                <th class="px-4 py-3">Saved by</th>
                                <th class="px-4 py-3">Created</th>
                                <th class="px-4 py-3">Rows</th>
                                <th class="px-4 py-3">Columns</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!assessmentsWithDerived.length">
                                <td colspan="7" class="px-4 py-10 text-center text-xs uppercase tracking-[0.4em] text-slate-400">
                                    No quarterly assessments have been saved yet.
                                </td>
                            </tr>
                            <tr
                                v-for="assessment in assessmentsWithDerived"
                                :key="assessment.id"
                                class="hover:bg-slate-50 cursor-pointer"
                                @click="handleRowClick(assessment)"
                            >
                                <td class="px-4 py-3 font-medium text-slate-900">{{ formatSection(assessment) }}</td>
                                <td class="px-4 py-3">{{ formatQuarter(assessment) }}</td>
                                <td class="px-4 py-3">{{ formatSchoolYear(assessment) }}</td>
                                <td class="px-4 py-3">{{ assessment.user?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatTimestamp(assessment.created_at) }}</td>
                                <td class="px-4 py-3">{{ assessment.rows }}</td>
                                <td class="px-4 py-3">
                                    <span class="block max-w-xs truncate text-[0.65rem] text-slate-500">
                                        {{ assessment.columns }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MainAuthLayout>
</template>

