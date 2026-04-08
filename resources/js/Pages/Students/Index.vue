<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import StudentMainContent from '@/Components/Students/StudentMainContent.vue';

const props = defineProps({
    enrollments: {
        type: Array,
        default: () => [],
    },
    gradeLevels: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            grade_level: '',
            section: '',
            search: '',
        }),
    },
});
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <StudentMainContent
                :enrollments="enrollments"
                :grade-levels="gradeLevels"
                :filters="filters"
            />
        </div>
    </MainAuthLayout>
</template>
