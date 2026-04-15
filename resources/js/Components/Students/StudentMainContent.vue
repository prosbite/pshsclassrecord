<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { formatDate } from '@/Composables/utilities.js';

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

const search = ref('');
const selectedGrade = ref('');
const selectedSection = ref('');
const filtersInitialized = ref(false);
let filterTimer;

watch(
    () => props.filters,
    (filters) => {
        selectedGrade.value = filters?.grade_level ?? '';
        selectedSection.value = filters?.section ?? '';
        search.value = filters?.search ?? '';
        filtersInitialized.value = true;
    },
    { immediate: true, deep: true }
);
const registerFileInput = ref(null);
const registerFileName = ref('');
const registerUploadInProgress = ref(false);
const registerUploadFeedback = ref('');
const updateEmailsFileInput = ref(null);
const updateEmailsFileName = ref('');
const updateEmailsInProgress = ref(false);
const updateEmailsUploadFeedback = ref('');

const gradeSections = computed(() => {
    return props.gradeLevels.reduce((acc, level) => {
        if (!level.grade_level) {
            return acc;
        }

        acc[level.grade_level] = (level.sections ?? [])
            .map((section) => section.section_name)
            .filter(Boolean);

        return acc;
    }, {});
});

const allSections = computed(() => {
    return [
        ...new Set(
            props.gradeLevels.flatMap((level) =>
                (level.sections ?? []).map((section) => section.section_name)
            )
        ),
    ].filter(Boolean);
});

const availableSections = computed(() => {
    if (!selectedGrade.value) {
        return allSections.value;
    }

    return gradeSections.value[selectedGrade.value] ?? [];
});

watch(selectedGrade, () => {
    if (
        selectedGrade.value &&
        selectedSection.value &&
        !availableSections.value.includes(selectedSection.value)
    ) {
        selectedSection.value = '';
        pushFilters();
    }
});

const structuredStudents = computed(() => {
    return props.enrollments.map((enrollment) => {
        const learner = enrollment?.learner ?? {};
        const section = enrollment?.section ?? {};
        const gradeLevel = section?.grade_level ?? {};
        const name = [learner.first_name, learner.middle_name, learner.last_name]
            .filter(Boolean)
            .join(' ');

        const middleInitial = learner.middle_name
            ? `${learner.middle_name.trim().charAt(0)}.`
            : '';
        const formattedName = learner.last_name
            ? `${learner.last_name}, ${learner.first_name || ''}${middleInitial ? ` ${middleInitial}` : ''}`
            : name || learner.email || 'Learner';
        const username = learner.user?.username ?? '—';

        return {
            id: enrollment.id,
            name: formattedName,
            username,
            email: learner.user?.email ?? learner.email ?? '—',
            status: enrollment.status ?? learner.status ?? 'Unknown',
            grade_level: gradeLevel.grade_level ?? '',
            section: section.section_name ?? '',
            created_at: enrollment.created_at,
        };
    });
});

const statusSummary = computed(() => {
    return structuredStudents.value.reduce((acc, student) => {
        const key = student.status || 'Unknown';
        acc[key] = (acc[key] ?? 0) + 1;
        return acc;
    }, {});
});

function pushFilters() {
    if (!filtersInitialized.value) {
        return;
    }

    if (filterTimer) {
        clearTimeout(filterTimer);
    }

    filterTimer = setTimeout(() => {
        const query = {};
        if (selectedGrade.value) {
            query.grade_level = selectedGrade.value;
        }
        if (selectedSection.value) {
            query.section = selectedSection.value;
        }
        if (search.value) {
            query.search = search.value;
        }

        router.visit(route('students'), {
            method: 'get',
            data: query,
            preserveState: true,
            replace: true,
        });
    }, 300);
};

const triggerRegisterCsvUpload = () => {
    registerFileInput.value?.click();
};

const triggerUpdateEmailsUpload = () => {
    updateEmailsFileInput.value?.click();
};

const handleRegisterCsvUpload = async (event) => {
    const file = event.target?.files?.[0];
    if (!file) {
        return;
    }

    registerFileName.value = file.name;
    await uploadCsv(file, route('students.bulk-register'), registerUploadInProgress, registerUploadFeedback);
    event.target.value = '';
};

const handleUpdateEmailsUpload = async (event) => {
    const file = event.target?.files?.[0];
    if (!file) {
        return;
    }

    updateEmailsFileName.value = file.name;
    await uploadCsv(
        file,
        route('students.bulk-update-emails'),
        updateEmailsInProgress,
        updateEmailsUploadFeedback
    );
    event.target.value = '';
};

const uploadCsv = async (file, endpoint, inProgressRef, feedbackRef) => {
    inProgressRef.value = true;
    feedbackRef.value = '';

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                Accept: 'application/json',
            },
            body: formData,
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.error ?? 'Upload failed');
        }

        feedbackRef.value = payload.message ?? 'Upload completed';
    } catch (error) {
        feedbackRef.value = error.message;
    } finally {
        inProgressRef.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4 rounded-3xl bg-white px-6 py-6 shadow-xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.4em] text-slate-400">Students</p>
                    <h2 class="text-2xl font-semibold text-slate-900">Class Registry</h2>
                    <p class="text-sm text-slate-500">
                        {{ structuredStudents.length }} scholars tracked in the class record.
                    </p>
                </div>

                <div class="flex flex-col items-end gap-3">
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 transition hover:from-cyan-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-cyan-400"
                            @click="triggerRegisterCsvUpload"
                            :disabled="registerUploadInProgress"
                        >
                            Register Students
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                            @click="triggerUpdateEmailsUpload"
                            :disabled="updateEmailsInProgress"
                        >
                            Update Emails
                        </button>
                    </div>
                    <div class="space-y-1 text-right text-xs text-slate-500">
                        <p v-if="registerUploadInProgress">Uploading student records…</p>
                        <p v-else-if="registerUploadFeedback">{{ registerUploadFeedback }}</p>
                        <p v-else-if="registerFileName">Selected register file: {{ registerFileName }}</p>
                        <p v-if="updateEmailsInProgress">Updating student emails…</p>
                        <p v-else-if="updateEmailsUploadFeedback">{{ updateEmailsUploadFeedback }}</p>
                        <p v-else-if="updateEmailsFileName">
                            Selected email file: {{ updateEmailsFileName }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <span
                    v-for="(count, status) in statusSummary"
                    :key="status"
                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600"
                >
                    {{ status }} · {{ count }}
                </span>
            </div>

            <div class="flex flex-wrap gap-3">
                    <label class="block w-full max-w-xs">
                        <span class="sr-only">Grade level</span>
                        <select
                            v-model="selectedGrade"
                            @change="pushFilters"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        >
                        <option value="">All grade levels</option>
                        <option
                            v-for="grade in Object.keys(gradeSections)"
                            :key="grade"
                            :value="grade"
                        >
                            {{ grade }}
                        </option>
                    </select>
                </label>

                    <label class="block w-full max-w-xs">
                        <span class="sr-only">Section</span>
                        <select
                            v-model="selectedSection"
                            @change="pushFilters"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        >
                        <option value="">All sections</option>
                        <option
                            v-for="section in availableSections"
                            :key="section"
                            :value="section"
                        >
                            {{ section }}
                        </option>
                    </select>
                </label>
            </div>

            <label class="w-full sm:w-64">
                <span class="sr-only">Search students</span>
                <input
                    v-model="search"
                    @input="pushFilters"
                    type="text"
                    placeholder="Search by name, username, email, grade, section, status"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-slate-400 focus:outline-none"
                />
            </label>
        </div>

        <div class="overflow-hidden rounded-3xl bg-white shadow-lg">
                <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-6 py-4 font-semibold">ID</th>
                            <th class="px-6 py-4 font-semibold">Name</th>
                            <th class="px-6 py-4 font-semibold">Username</th>
                            <th class="px-6 py-4 font-semibold">Grade Level</th>
                            <th class="px-6 py-4 font-semibold">Section</th>
                            <th class="px-6 py-4 font-semibold">Email</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="student in structuredStudents"
                            :key="student.id"
                            class="border-b last:border-b-0 odd:bg-white even:bg-slate-50"
                        >
                            <td class="px-6 py-4 font-mono text-xs uppercase text-slate-500">
                                {{ student.id }}
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                                {{ student.name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ student.username || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ student.grade_level || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ student.section || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ student.email }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-xs font-semibold',
                                        student.status === 'Active'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-amber-100 text-amber-700',
                                    ]"
                                >
                                    {{ student.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">
                                {{ formatDate(student.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p
                    v-if="!structuredStudents.length"
                    class="px-6 py-6 text-center text-sm text-slate-500"
                >
                    No students match your filters yet.
                </p>
            </div>
        </div>
        <input
            ref="registerFileInput"
            type="file"
            accept=".csv,.xlsx,.xls"
            class="hidden"
            @change="handleRegisterCsvUpload"
        />
        <input
            ref="updateEmailsFileInput"
            type="file"
            accept=".csv"
            class="hidden"
            @change="handleUpdateEmailsUpload"
        />
    </div>
</template>
