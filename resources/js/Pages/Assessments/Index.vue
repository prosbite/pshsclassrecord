<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import AssessmentMainContent from '@/Components/Assessments/AssessmentMainContent.vue';

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
            <AssessmentMainContent
                :assessments="assessments"
                :sections="sections"
                :school-year="schoolYear"
                :section-filter="sectionFilter"
            />
        </div>
    </MainAuthLayout>
</template>
