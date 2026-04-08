<script setup>
import { Link } from '@inertiajs/vue3';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import AssessmentSummary from '@/Components/Assessments/AssessmentSummary.vue';

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
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Assessments</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Assessment summary</h1>
                        <p class="text-sm text-slate-500">
                            Get a quick breakdown of every assessment recorded in the active school year.
                        </p>
                    </div>
                    <Link
                        :href="route('assessments.index', props.sectionFilter && props.sectionFilter !== 'all' ? { section: props.sectionFilter } : {})"
                        class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Back to assessments
                    </Link>
                </div>
            </div>
            <AssessmentSummary
                :assessments="props.assessments"
                :school-year="props.schoolYear"
                :sections="props.sections"
                :section-filter="props.sectionFilter"
            />
        </div>
    </MainAuthLayout>
</template>
