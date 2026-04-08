<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

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

    router.visit(route('assessments.summary'), {
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

const tableRows = computed(() => {
    return filteredAssessments.value.map((assessment) => {
        const learners = assessment.learners_count ?? 0;
        const base = Math.max(learners, 1);
        const percentage = assessment.assessmentType?.percentage ?? null;
        const percText = percentage !== null ? `${percentage}%` : '—';
        const weighted = percentage !== null ? `${(percentage * 0.25).toFixed(2)}%` : '—';
        const gradeLevel = assessment.section?.grade_level?.grade_level || '—';
        const teacherName = assessment.user?.name ?? 'Unassigned';
        const sectionName = assessment.section?.section_name || 'General';
        const initials = (teacherName && teacherName[0]) || '—';
        const adjectives =
            percentage === null
                ? 'Needs data'
                : percentage >= 95
                    ? 'Outstanding'
                    : percentage >= 85
                        ? 'Very Satisfactory'
                        : percentage >= 70
                            ? 'Satisfactory'
                            : 'Needs Improvement';

        const computedNumber = (factor) => Math.round(base * factor);
        const maybeNumber = (value, fallback = '—') => (value || value === 0 ? value : fallback);

        return {
            id: assessment.id,
            familyName: sectionName,
            givenName: teacherName,
            middleInitial: initials,
            lt1: computedNumber(0.8),
            lt1Total: computedNumber(1),
            lt1Percent: percText,
            lt1Weight: weighted,
            lt2: computedNumber(0.75),
            lt2Total: computedNumber(0.95),
            lt2Percent: percText,
            lt2Weight: weighted,
            aa1: computedNumber(0.6),
            aa2: computedNumber(0.4),
            aaTotal: computedNumber(1),
            aaPercent: percText,
            aaWeight: weighted,
            fa1: computedNumber(0.25),
            fa2: computedNumber(0.25),
            fa3: computedNumber(0.25),
            faTotal: computedNumber(0.75),
            faPercent: percText,
            faWeight: weighted,
            twPercent: percText,
            ge: gradeLevel,
            twoThirds: maybeNumber(Math.round(base * 0.67)),
            gScore: maybeNumber(percentage ? (percentage * 0.7).toFixed(2) : null),
            oneThird: maybeNumber(Math.round(base * 0.33)),
            trunc: maybeNumber(Math.trunc(percentage ?? 0)),
            finalGe: gradeLevel,
            adjectival: adjectives,
        };
    });
});

const schoolYear = computed(() => props.schoolYear);
</script>

<template>
    <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Assessment summary</p>
                <h3 class="text-lg font-semibold text-slate-900">Quarterly breakdown</h3>
            </div>
            <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center">
                <span class="text-xs text-slate-500">
                    {{ filteredAssessments.length }} assessments · {{ schoolYear ? schoolYear.year_start + '-' + schoolYear.year_end : 'No school year' }}
                </span>
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
            </div>
        </div>
        <div class="px-6 pb-4">
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full">
                    <table class="min-w-full divide-y divide-slate-100 text-xs text-slate-600 table-fixed">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr class="border-b border-slate-200">
                                <th rowspan="2" colspan="3" class="px-4 py-3 text-left font-semibold border-r border-slate-200 w-40 sticky left-0 z-10 bg-white">
                                    Mathematics 6
                                </th>
                                <th colspan="3" class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    Long Test 1
                                </th>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    25%
                                </th>
                                <th colspan="3" class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    Long Test 2
                                </th>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    25%
                                </th>
                                <th colspan="4" class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    Alternative Assessments
                                </th>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    25%
                                </th>
                                <th colspan="5" class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    Formative Assessments
                                </th>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                    25%
                                </th>
                                <th class="px-4 py-3 text-center font-semibold border-r border-slate-200">
                                </th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    3rd Quarter
                                </th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    Second Quarter
                                </th>
                                <th colspan="2" class="px-4 py-3 text-center font-semibold border-r border-slate-200 bg-emerald-50">
                                    Final Grade
                                </th>
                                <th rowspan="3" class="px-6 py-3 text-center font-semibold border-l border-slate-200 bg-amber-50 w-40">
                                    Adjectival Equivalent
                                </th>
                            </tr>
                            <tr class="text-[10px] border-b border-slate-100 bg-white text-slate-500">
                                <th class="px-2 py-2 border-r border-slate-100">LT1</th>
                                <th class="px-2 py-2 border-r border-slate-100">T</th>
                                <th class="px-2 py-2 border-r border-slate-100">%</th>
                                <th class="px-2 py-2 border-r border-slate-100 font-medium text-emerald-500">W%</th>
                                <th class="px-2 py-2 border-r border-slate-100">LT2</th>
                                <th class="px-2 py-2 border-r border-slate-100">T</th>
                                <th class="px-2 py-2 border-r border-slate-100">%</th>
                                <th class="px-2 py-2 border-r border-slate-100 font-medium text-emerald-500">W%</th>
                                <th class="px-2 py-2 border-r border-slate-100">AA1</th>
                                <th class="px-2 py-2 border-r border-slate-100">AA2</th>
                                <th class="px-2 py-2 border-r border-slate-100">T</th>
                                <th class="px-2 py-2 border-r border-slate-100">%</th>
                                <th class="px-2 py-2 border-r border-slate-100 font-medium text-emerald-500">W%</th>
                                <th class="px-2 py-2 border-r border-slate-100">FA1</th>
                                <th class="px-2 py-2 border-r border-slate-100">FA2</th>
                                <th class="px-2 py-2 border-r border-slate-100">FA3</th>
                                <th class="px-2 py-2 border-r border-slate-100">T</th>
                                <th class="px-2 py-2 border-r border-slate-100">%</th>
                                <th class="px-2 py-2 border-slate-100 font-medium text-emerald-500">W%</th>
                                <th class="px-2 py-2 border-r border-slate-100 font-bold text-gray-800">TW%</th>
                                <th class="px-2 py-2 border-r border-slate-100">GE</th>
                                <th class="px-2 py-2 border-r border-slate-100">2/3</th>
                                <th class="px-2 py-2 border-r border-slate-100">G</th>
                                <th class="px-2 py-2 border-r border-slate-100">1/3</th>
                                <th class="px-2 py-2 border-r border-slate-100">Trunc</th>
                                <th class="px-2 py-2 border-r border-slate-100">GE</th>
                            </tr>
                            <tr class="text-[10px] border-b border-slate-100 bg-gray-200 text-slate-500">
                                <th class="px-2 py-2 border-r border-slate-100 w-150" style="width:300px">Family Name</th>
                                <th class="px-2 py-2 border-r border-slate-100">Given Name</th>
                                <th class="px-2 py-2 border-r border-slate-100">M.I.</th>
                                <th class="px-2 py-2 border-r border-slate-100">40</th>
                                <th class="px-2 py-2 border-r border-slate-100">40</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th class="px-2 py-2 border-r border-slate-100">40</th>
                                <th class="px-2 py-2 border-r border-slate-100">40</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th class="px-2 py-2 border-r border-slate-100">25</th>
                                <th class="px-2 py-2 border-r border-slate-100">25</th>
                                <th class="px-2 py-2 border-r border-slate-100">50</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th class="px-2 py-2 border-r border-slate-100">25</th>
                                <th class="px-2 py-2 border-r border-slate-100">25</th>
                                <th class="px-2 py-2 border-r border-slate-100">25</th>
                                <th class="px-2 py-2 border-r border-slate-100">75</th>
                                <th class="px-2 py-2 border-r border-slate-100">100%</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th class="px-2 py-2 border-r border-slate-100">100%</th>
                                <th class="px-2 py-2 border-r border-slate-100">STA-9</th>
                                <th class="px-2 py-2 border-r border-slate-100">0.67</th>
                                <th class="px-2 py-2 border-r border-slate-100">1.00</th>
                                <th class="px-2 py-2 border-r border-slate-100">0.33</th>
                                <th class="px-2 py-2 border-r border-slate-100">1.00</th>
                                <th class="px-2 py-2 border-r border-slate-100">STA-9</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                            <tr
                                v-for="row in tableRows"
                                :key="row.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 border-r border-slate-100 font-medium sticky left-0 bg-white z-10">{{ row.familyName }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.givenName }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.middleInitial }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt1 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt1Total }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt1Percent }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ row.lt1Weight }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt2 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt2Total }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt2Percent }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ row.lt2Weight }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.aa1 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.aa2 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.aaTotal }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.aaPercent }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ row.aaWeight }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.fa1 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.fa2 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.fa3 }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.faTotal }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.faPercent }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ row.faWeight }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.twPercent }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.ge }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.twoThirds }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.gScore }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.oneThird }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.trunc }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.finalGe }}</td>
                                <td class="px-4 py-3 text-center border-r border-slate-100 font-medium text-emerald-500">{{ row.adjectival }}</td>
                            </tr>
                            <tr v-if="!tableRows.length" class="bg-white">
                                <td colspan="30" class="px-4 py-6 text-center text-sm text-slate-400">
                                    No quarterly breakdown available yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
    .w-150 {
        width: 150px!important;
    }
    .table-fixed {
        table-layout: fixed;
    }
</style>
