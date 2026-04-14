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
    section: {
        type: Object,
        default: 'all',
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
        return []
    }

    if (selection === 'unassigned') {
        return []
    }

    return props.assessments.filter((assessment) => String(assessment.section?.id) === selection);
});

// const tableRows = computed(() => {
//     return filteredAssessments.value.map((assessment) => {
//         const learners = assessment.learners_count ?? 0;
//         const base = Math.max(learners, 1);
//         const percentage = assessment.assessmentType?.percentage ?? null;
//         const percText = percentage !== null ? `${percentage}%` : '—';
//         const weighted = percentage !== null ? `${(percentage * 0.25).toFixed(2)}%` : '—';
//         const gradeLevel = assessment.section?.grade_level?.grade_level || '—';
//         const teacherName = assessment.user?.name ?? 'Unassigned';
//         const sectionName = assessment.section?.section_name || 'General';
//         const initials = (teacherName && teacherName[0]) || '—';
//         const adjectives =
//             percentage === null
//                 ? 'Needs data'
//                 : percentage >= 95
//                     ? 'Outstanding'
//                     : percentage >= 85
//                         ? 'Very Satisfactory'
//                         : percentage >= 70
//                             ? 'Satisfactory'
//                             : 'Needs Improvement';

//         const computedNumber = (factor) => Math.round(base * factor);
//         const maybeNumber = (value, fallback = '—') => (value || value === 0 ? value : fallback);

//         return {
//             id: assessment.id,
//             familyName: sectionName,
//             givenName: teacherName,
//             middleInitial: initials,
//             lt1: computedNumber(0.8),
//             lt1Total: computedNumber(1),
//             lt1Percent: percText,
//             lt1Weight: weighted,
//             lt2: computedNumber(0.75),
//             lt2Total: computedNumber(0.95),
//             lt2Percent: percText,
//             lt2Weight: weighted,
//             aa1: computedNumber(0.6),
//             aa2: computedNumber(0.4),
//             aaTotal: computedNumber(1),
//             aaPercent: percText,
//             aaWeight: weighted,
//             fa1: computedNumber(0.25),
//             fa2: computedNumber(0.25),
//             fa3: computedNumber(0.25),
//             faTotal: computedNumber(0.75),
//             faPercent: percText,
//             faWeight: weighted,
//             twPercent: percText,
//             ge: gradeLevel,
//             twoThirds: maybeNumber(Math.round(base * 0.67)),
//             gScore: maybeNumber(percentage ? (percentage * 0.7).toFixed(2) : null),
//             oneThird: maybeNumber(Math.round(base * 0.33)),
//             trunc: maybeNumber(Math.trunc(percentage ?? 0)),
//             finalGe: gradeLevel,
//             adjectival: adjectives,
//         };
//     });
// });
const tableRows = computed(() => {
   const longTests = props.assessments.filter(a => a.assessment_type.name === 'Long Test');
   const alternativeAssessments = props.assessments.filter(a => a.assessment_type.name === 'Alternative Assessment');
   const formativeAssessments = props.assessments.filter(a => a.assessment_type.name === 'Formative Assessment');
   const students = props.section?.enrollments ?? []

   const rows = students.map(enrollment => {
       const learner = enrollment.learner;
       const row = {
            id: enrollment.id,
            familyName: learner.last_name,
            givenName: learner.first_name,
            middleInitial: learner.middle_name ? learner.middle_name[0] : '',
            lt1: {
                score: longTests[0]?.learners?.find(s => s.id === learner.id)?.pivot.score ?? '—',
                perfectScore: longTests[0]?.perfect_score ?? '—',
                percent: longTests[0]?.scores?.find(s => s.learner_id === learner.id)?.percent ?? '—',
            },
            lt2: {
                score: longTests[1]?.learners?.find(s => s.id === learner.id)?.pivot.score ?? '—',
                perfectScore: longTests[1]?.perfect_score ?? '—',
                percent: longTests[1]?.scores?.find(s => s.learner_id === learner.id)?.percent ?? '—',
            },
            alternativeAssessments: alternativeAssessments.map(aa => aa.learners?.find(s => s.id === learner.id)?.pivot.score ?? '—'),
            formativeAssessments: formativeAssessments.map(fa => fa.learners?.find(s => s.id === learner.id)?.pivot.score ?? '—'),
       };
       return row;
   });
   return {rows, longTests, alternativeAssessments, formativeAssessments};
});
const schoolYear = computed(() => props.schoolYear);
const totalPerfectScore = computed(() => {
    const lt1Perfect = tableRows.value.longTests[0]?.perfect_score || 0;
    const lt2Perfect = tableRows.value.longTests[1]?.perfect_score || 0;
    const aaPerfect = tableRows.value.alternativeAssessments.reduce((sum, aa) => sum + (aa.perfect_score || 0), 0);
    const faPerfect = tableRows.value.formativeAssessments.reduce((sum, fa) => sum + (fa.perfect_score || 0), 0);
    return { lt1Perfect, lt2Perfect, aaPerfect, faPerfect, total: lt1Perfect + lt2Perfect + aaPerfect + faPerfect };
});
const totalScore = (scores) => {
    return scores.reduce((sum, score) => sum + parseFloat(score || 0), 0);
}
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
                                <th class="px-2 py-2 border-r border-slate-100">{{ tableRows.longTests?.[0].perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">{{ tableRows.longTests?.[0].perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th class="px-2 py-2 border-r border-slate-100">{{ tableRows.longTests?.[1].perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">{{ tableRows.longTests?.[1].perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th v-for="aa in tableRows.alternativeAssessments" :key="aa.id" class="px-2 py-2 border-r border-slate-100">{{ aa.perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">{{ totalPerfectScore.aaPerfect }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">100</th>
                                <th class="px-2 py-2 border-r border-slate-100">25%</th>
                                <th v-for="fa in tableRows.formativeAssessments" :key="fa.id" class="px-2 py-2 border-r border-slate-100">{{ fa.perfect_score }}</th>
                                <th class="px-2 py-2 border-r border-slate-100">{{ totalPerfectScore.faPerfect }}</th>
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
                                v-for="row in tableRows.rows"
                                :key="row.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 border-r border-slate-100 font-medium sticky left-0 bg-white z-10">{{ row.familyName }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.givenName }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.middleInitial }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt1.score }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt1.score }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ ((row.lt1.score / totalPerfectScore.lt1Perfect)*100 || 0).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ (((row.lt1.score / totalPerfectScore.lt1Perfect)*100 || 0)*0.25 ).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt2.score }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.lt2.score }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ ((row.lt2.score / totalPerfectScore.lt2Perfect)*100 || 0).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ (((row.lt2.score / totalPerfectScore.lt2Perfect)*100 || 0)*0.25 ).toFixed(2) }}%</td>
                                <td v-for="aa in row.alternativeAssessments" :key="aa.id" class="px-3 py-3 text-center border-r border-slate-100">{{ aa }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ totalScore(row.alternativeAssessments).toFixed(2) }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ ((totalScore(row.alternativeAssessments) / totalPerfectScore.aaPerfect)*100 || 0).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ (((totalScore(row.alternativeAssessments) / totalPerfectScore.aaPerfect)*100 || 0)*0.25 ).toFixed(2) }}%</td>
                                <td v-for="fa in row.formativeAssessments" :key="fa.id" class="px-3 py-3 text-center border-r border-slate-100">{{ fa }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ totalScore(row.formativeAssessments).toFixed(2) }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ ((totalScore(row.formativeAssessments) / totalPerfectScore.faPerfect)*100 || 0).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-emerald-500 font-medium">{{ (((totalScore(row.formativeAssessments) / totalPerfectScore.faPerfect)*100 || 0)*0.25 ).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100 text-orange-500 font-medium">{{ ((((row.lt1.score / totalPerfectScore.lt1Perfect)*100 || 0)*0.25 ) + (((row.lt2.score / totalPerfectScore.lt2Perfect)*100 || 0)*0.25 ) + (((totalScore(row.alternativeAssessments) / totalPerfectScore.aaPerfect)*100 || 0)*0.25 ) + (((totalScore(row.formativeAssessments) / totalPerfectScore.faPerfect)*100 || 0)*0.25 )).toFixed(2) }}%</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.ge }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.twoThirds }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.gScore }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.oneThird }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.trunc }}</td>
                                <td class="px-3 py-3 text-center border-r border-slate-100">{{ row.finalGe }}</td>
                                <td class="px-4 py-3 text-center border-r border-slate-100 font-medium text-emerald-500">{{ row.adjectival }}</td>
                            </tr>
                            <tr v-if="!tableRows.rows.length" class="bg-white">
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
