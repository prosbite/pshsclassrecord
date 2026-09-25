<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import AssessmentForm from '@/Components/Assessments/AssessmentForm.vue';

const props = defineProps({
    schoolYear: {
        type: Object,
        default: null,
    },
    assessmentTypes: {
        type: Array,
        default: () => [],
    },
    quarters: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    assessment: {
        type: Object,
        required: true,
    },
    learnerScores: {
        type: Object,
        default: () => ({}),
    },
    learnerTentatives: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <AssessmentForm
                :school-year="props.schoolYear"
                :assessment-types="props.assessmentTypes"
                :quarters="props.quarters"
                :sections="props.sections"
                :assessment="props.assessment"
                :initial-scores="props.learnerScores"
                :initial-tentatives="props.learnerTentatives"
            />
        </div>
    </MainAuthLayout>
</template>
