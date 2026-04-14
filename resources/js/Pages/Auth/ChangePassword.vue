<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Change Password" />

    <StudentLayout>
        <div class="flex min-h-[60vh] items-center justify-center">
            <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white px-10 py-10 shadow-lg shadow-slate-200/40">
                <div class="mb-6 text-center">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Security</p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900">Change password</h1>
                    <p class="text-sm text-slate-500">
                        Update your PSHS account password to keep your portal access secure.
                    </p>
                </div>

                <UpdatePasswordForm />

            </div>
        </div>
    </StudentLayout>
</template>
