<script setup>
import QuestionForm from '@/Components/Exercises/QuestionForm.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    question: {
        type: Object,
        required: true,
    },
    topics: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <MainAuthLayout>
        <QuestionForm
            :question="props.question"
            :topics="props.topics"
        />
    </MainAuthLayout>
</template>
