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
    topics: {
        type: Array,
        default: () => [],
    },
    questionnaires: {
        type: Array,
        default: () => [],
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
                :topics="props.topics"
                :questionnaires="props.questionnaires"
            />
        </div>
    </MainAuthLayout>
</template>
