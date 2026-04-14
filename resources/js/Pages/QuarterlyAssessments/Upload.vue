<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import QuarterlyUploadContent from '@/Components/QuarterlyAssessments/QuarterlyUploadContent.vue';

const props = defineProps({
    schoolYear: {
        type: Object,
        default: null,
    },
    sections: {
        type: Array,
        default: () => [],
    },
    quarters: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <QuarterlyUploadContent
                :school-year="props.schoolYear"
                :sections="props.sections"
                :quarters="props.quarters"
            />
        </div>
    </MainAuthLayout>
</template>
