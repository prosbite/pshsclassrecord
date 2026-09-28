<script setup>
import QuestionForm from '@/Components/Exercises/QuestionForm.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';

const props = defineProps({
    topics: {
        type: Array,
        default: () => [],
    },
    selectedTopicId: {
        type: Number,
        default: null,
    },
});
</script>

<template>
    <MainAuthLayout>
        <QuestionForm
            :topics="props.topics"
            :selected-topic-id="props.selectedTopicId"
        />
    </MainAuthLayout>
</template>
