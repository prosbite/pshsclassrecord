<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import AssessmentDetail from '@/Components/Assessments/AssessmentDetail.vue';

const props = defineProps({
    assessment: {
        type: Object,
        required: true,
    },
    learners: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <AssessmentDetail :assessment="assessment" :learners="learners" />
        </div>
    </MainAuthLayout>
</template>
