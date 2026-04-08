<script setup>
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <MainAuthLayout>
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-4">Welcome to the Dashboard</h1>
            <p class="text-gray-600">This is your dashboard where you can manage your account and view important information.</p>
        </div>
    </MainAuthLayout>
</template>
