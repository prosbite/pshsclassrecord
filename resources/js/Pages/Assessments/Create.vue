<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import MainAuthLayout from '@/Layouts/MainAuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatDate } from '@/Composables/utilities.js';

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
});

const page = usePage();
const todayIso = new Date().toISOString().slice(0, 10);
const form = useForm({
    title: '',
    assessment_type_id: null,
    quarter_id: null,
    section_id: null,
    school_year_id: props.schoolYear?.id ?? null,
    user_id: null,
    learner_scores: [],
    assessment_date: todayIso,
    perfect_score: 100,
});

watch(
    () => page.props.value,
    (pageProps) => {
        const userId = pageProps?.auth?.user?.id ?? null;
        form.user_id = userId;
    },
    { immediate: true }
);

const currentUserName = computed(
    () => page.props.value?.auth?.user?.name ?? 'Current user'
);

watch(
    () => props.schoolYear,
    (schoolYear) => {
        form.school_year_id = schoolYear?.id ?? null;
    },
    { immediate: true }
);

watch(
    () => props.assessmentTypes,
    (types) => {
        if (!form.assessment_type_id && types.length) {
            form.assessment_type_id = types[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => props.quarters,
    (quarters) => {
        if (!form.quarter_id && quarters.length) {
            form.quarter_id = quarters[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => props.sections,
    (sections) => {
        if (!form.section_id && sections.length) {
            form.section_id = sections[0].id;
        }
    },
    { immediate: true }
);

const learners = ref([]);
const learnersLoading = ref(false);
const learnerError = ref('');
const learnerScores = reactive({});
const csvStatus = ref('');
const csvWarnings = ref([]);
const scoreUploadRef = ref(null);
const parsingCsv = ref(false);
const normalizeName = (value) =>
    (value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');

const selectedLearners = computed(() =>
    learners.value.map((enrollment) => {
        const learner = enrollment.learner ?? {};
        const middleInitial = learner.middle_name ? `${learner.middle_name.trim().charAt(0)}.` : '';
        const fullName = learner.last_name
            ? `${learner.last_name}, ${learner.first_name ?? ''}${middleInitial ? ` ${middleInitial}` : ''}`
            : [learner.first_name, learner.last_name].filter(Boolean).join(' ') || 'Learner';

        return {
            id: learner.id ?? enrollment.learner_id,
            name: fullName,
            email: learner.email,
            status: enrollment.status ?? learner.status ?? 'Unknown',
        };
    })
);

const hasLearners = computed(() => selectedLearners.value.length > 0);

const resetLearnerScores = (list) => {
    Object.keys(learnerScores).forEach((key) => delete learnerScores[key]);

    list.forEach((enrollment) => {
        const learnerId = enrollment.learner?.id ?? enrollment.learner_id;
        if (learnerId) {
            learnerScores[learnerId] = 0;
        }
    });
};

const fetchSectionLearners = async (sectionId) => {
    if (!sectionId) {
        learners.value = [];
        resetLearnerScores([]);
        learnerError.value = '';
        learnersLoading.value = false;
        return;
    }

    learnersLoading.value = true;
    learnerError.value = '';

    try {
        const response = await fetch(route('assessments.section-learners', { section_id: sectionId }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Unable to load learners for the selected section.');
        }

        const data = await response.json();
        learners.value = data;
        resetLearnerScores(data);
    } catch (error) {
        learnerError.value = error.message;
        learners.value = [];
        resetLearnerScores([]);
    } finally {
        learnersLoading.value = false;
    }
};

const currentAssessmentType = computed(() =>
    props.assessmentTypes.find((type) => type.id === form.assessment_type_id)
);

const triggerScoreUpload = () => {
    scoreUploadRef.value?.click();
};

const handleScoresCsv = async (event) => {
    const file = event?.target?.files?.[0];
    if (!file) {
        return;
    }

    parsingCsv.value = true;
    csvStatus.value = 'Parsing file…';
    csvWarnings.value = [];

    try {
        const text = await file.text();
        const lines = text.split(/\r?\n/).map((line) => line.trim()).filter((line) => line.length);
        if (!lines.length) {
            throw new Error('The selected CSV file is empty.');
        }

        const headerCols = lines[0].split(',').map((col) => col.trim().toLowerCase());
        const required = ['last_name', 'first_name', 'assessment_type', 'scores'];
        const missing = required.filter((col) => !headerCols.includes(col));

        if (missing.length) {
            throw new Error(`CSV missing required columns: ${missing.join(', ')}`);
        }

        const index = (column) => headerCols.indexOf(column);
        let matched = 0;
        let notFound = 0;
        let typeSet = false;
        let dateSet = false;

        const hasDateColumn = headerCols.includes('assessment_date');

        lines.slice(1).forEach((line) => {
            const values = line.split(',').map((value) => value.trim());
            if (!values.length) {
                return;
            }

            const lastName = values[index('last_name')] ?? '';
            const firstName = values[index('first_name')] ?? '';
            const scoreValue = values[index('scores')] ?? '';
            const assessmentTypeName = (values[index('assessment_type')] ?? '').trim();
            const typeKey = assessmentTypeName.toLowerCase();
            const dateRaw = hasDateColumn ? values[index('assessment_date')] ?? '' : '';
            const lastNormalized = normalizeName(lastName);
            const firstNormalized = normalizeName(firstName);

            const learnerEntry = learners.value.find((entry) => {
                const learnerData = entry.learner ?? {};
                return (
                    normalizeName(learnerData.last_name) === lastNormalized &&
                    normalizeName(learnerData.first_name) === firstNormalized
                );
            });

            if (!learnerEntry) {
                notFound += 1;
                return;
            }
            const learnerId = learnerEntry.learner?.id ?? learnerEntry.learner_id;

            if (!learnerId) {
                notFound += 1;
                return;
            }

            const score = Number(scoreValue);
            learnerScores[learnerId] = Number.isNaN(score) ? 0 : score;
            matched += 1;

            if (typeKey && !typeSet) {
                const lookup = props.assessmentTypes.find((type) => type.name.toLowerCase() === typeKey);

                if (lookup) {
                    form.assessment_type_id = lookup.id;
                } else {
                    csvWarnings.value.push(
                        `The CSV selected type "${assessmentTypeName}" could not be matched with any configured assessment type.`
                    );
                }

                typeSet = true;
            }

            if (hasDateColumn && dateRaw && !dateSet) {
                const parsed = new Date(dateRaw);
                if (!Number.isNaN(parsed.valueOf())) {
                    form.assessment_date = parsed.toISOString().slice(0, 10);
                } else {
                    csvWarnings.value.push(
                        `The assessment date "${dateRaw}" from the CSV could not be parsed.`
                    );
                }

                dateSet = true;
            }
        });

        csvStatus.value = `Matched ${matched} learner${matched === 1 ? '' : 's'}. ${
            notFound ? `${notFound} rows skipped.` : ''
        }`;
    } catch (error) {
        csvStatus.value = 'Unable to import scores.';
        csvWarnings.value = [error.message];
    } finally {
        parsingCsv.value = false;
        if (scoreUploadRef.value) {
            scoreUploadRef.value.value = '';
        }
    }
};

watch(
    () => form.section_id,
    (sectionId) => {
        fetchSectionLearners(sectionId);
    },
    { immediate: true }
);

const sectionOptions = computed(() =>
    props.sections.map((section) => {
        const grade = section?.grade_level?.grade_level;
        const sectionName = section?.section_name ?? 'Section';
        const label = grade ? `${grade} · ${sectionName}` : sectionName;
        return {
            id: section.id,
            label,
        };
    })
);

const quarterOptions = computed(() =>
    props.quarters.map((quarter) => {
        const label = quarter.quarter ? `Quarter ${quarter.quarter}` : 'Quarter';
        const start = quarter.start_date ? new Date(quarter.start_date).toLocaleDateString() : null;
        const end = quarter.end_date ? new Date(quarter.end_date).toLocaleDateString() : null;
        const range = start && end ? `${start} — ${end}` : 'Dates pending';
        return {
            id: quarter.id,
            label,
            range,
        };
    })
);

const selectedQuarterSummary = computed(() => {
    const quarter = props.quarters.find((q) => q.id === form.quarter_id);

    if (!quarter) {
        return null;
    }

    const start = quarter.start_date ? formatDate(quarter.start_date) : null;
    const end = quarter.end_date ? formatDate(quarter.end_date) : null;

    return start && end ? `${start} — ${end}` : 'Dates pending';
});

const canSubmit = computed(
    () =>
        !!form.school_year_id &&
        !!form.assessment_type_id &&
        !!form.quarter_id &&
        !!form.section_id &&
        !form.processing
);

const handleSubmit = () => {
    form.learner_scores = Object.entries(learnerScores).map(([learnerId, score]) => ({
        learner_id: Number(learnerId),
        score: score === '' || score === null ? 0 : Number(score),
    }));

    form.post(route('assessments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            router.visit(route('assessments.index'));
        },
    });
};
</script>

<template>
    <MainAuthLayout>
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 shadow-lg sm:p-8">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Create assessment</p>
                        <h1 class="text-2xl font-semibold text-slate-900">Draft a new evaluation</h1>
                        <p class="text-sm text-slate-500">
                            Capture the title, type, quarter, and section for the assessment you are scheduling.
                        </p>
                    </div>
                    <Link
                        :href="route('assessments.index')"
                        class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Back to assessments
                    </Link>
                </div>

                <form class="mt-8 space-y-6" @submit.prevent="handleSubmit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Title</span>
                            <input
                                v-model="form.title"
                                type="text"
                                placeholder="E.g. Final Exam – Long Test"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            />
                            <InputError :message="form.errors.title" class="mt-1" />
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Assessment type</span>
                            <select
                                v-model="form.assessment_type_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            >
                                <option value="" disabled>Select type</option>
                                <option
                                    v-for="type in props.assessmentTypes"
                                    :key="type.id"
                                    :value="type.id"
                                >
                                    {{ type.name }} · {{ type.percentage ?? 0 }}%
                                </option>
                            </select>
                            <InputError :message="form.errors.assessment_type_id" class="mt-1" />
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Assessment date</span>
                            <input
                                v-model="form.assessment_date"
                                type="date"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            />
                            <InputError :message="form.errors.assessment_date" class="mt-1" />
                        </label>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <p class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Type preview</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">
                                {{ currentAssessmentType?.name ?? 'Select a type' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ currentAssessmentType ? `${currentAssessmentType.percentage}% of grade` : 'Percentage updates once a type is chosen.' }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Quarter</span>
                            <select
                                v-model="form.quarter_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            >
                                <option value="" disabled>Select quarter</option>
                                <option
                                    v-for="quarter in quarterOptions"
                                    :key="quarter.id"
                                    :value="quarter.id"
                                >
                                    {{ quarter.label }}
                                </option>
                            </select>
                            <p v-if="selectedQuarterSummary" class="mt-1 text-xs text-slate-500">
                                {{ selectedQuarterSummary }}
                            </p>
                            <InputError :message="form.errors.quarter_id" class="mt-1" />
                        </label>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-[0.4em] text-slate-400">Section</span>
                            <select
                                v-model="form.section_id"
                                class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            >
                                <option value="" disabled>Select section</option>
                                <option
                                    v-for="section in sectionOptions"
                                    :key="section.id"
                                    :value="section.id"
                                >
                                    {{ section.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.section_id" class="mt-1" />
                        </label>
                    </div>

                    <div class="rounded-3xl border border-slate-100 bg-slate-50 p-6 shadow-inner space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Section learners</p>
                                <p class="text-sm text-slate-600">
                                    {{ form.section_id ? `${selectedLearners.length} learner${selectedLearners.length === 1 ? '' : 's'}` : 'Pick a section to load learners' }}
                                </p>
                            </div>
                            <span
                                v-if="learnersLoading"
                                class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500"
                            >
                                Loading…
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                @click="triggerScoreUpload"
                                class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500"
                                :disabled="learnersLoading"
                            >
                                Upload scores CSV
                            </button>
                            <span class="text-xs text-slate-500">
                                Columns required: last_name, first_name, assessment_type, scores.
                            </span>
                            <span class="text-xs font-semibold text-slate-700" v-if="csvStatus">
                                {{ csvStatus }}
                            </span>
                        </div>
                        <input
                            type="file"
                            accept=".csv"
                            ref="scoreUploadRef"
                            class="hidden"
                            @change="handleScoresCsv"
                        />
                        <div v-if="csvWarnings.length" class="space-y-1">
                            <p class="text-xs text-amber-600" v-for="warning in csvWarnings" :key="warning">
                                {{ warning }}
                            </p>
                        </div>
                        <p
                            v-if="learnerError"
                            class="rounded-2xl bg-rose-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.4em] text-rose-600"
                        >
                            {{ learnerError }}
                        </p>
                        <p
                            v-else-if="!form.section_id"
                            class="text-xs text-slate-500"
                        >
                            Select a section so the enrolled learners can be scored.
                        </p>
                        <p
                            v-else-if="!hasLearners && !learnersLoading"
                            class="text-xs text-slate-500"
                        >
                            No learners are active in this section for the current school year.
                        </p>
                        <div
                            v-else
                            class="overflow-x-auto"
                        >
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-white text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 font-semibold">Learner</th>
                                        <th class="px-4 py-3 font-semibold">Email</th>
                                        <th class="px-4 py-3 font-semibold text-right">Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="learner in selectedLearners"
                                        :key="learner.id"
                                        class="border-t border-slate-100 bg-white"
                                    >
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                            {{ learner.name }}
                                            <div class="text-xs font-normal text-slate-500">
                                                {{ learner.status }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-500">
                                            {{ learner.email || '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <input
                                                v-model.number="learnerScores[learner.id]"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="w-24 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">School year</span>
                            <p class="mt-1 text-lg font-semibold text-slate-900">
                                {{ props.schoolYear ? `${props.schoolYear.year_start}-${props.schoolYear.year_end}` : 'Not set' }}
                            </p>
                            <p class="text-xs text-slate-500">Locked to the active school year.</p>
                        </div>

                        <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="text-[0.65rem] uppercase tracking-[0.4em] text-slate-400">Assigned teacher</span>
                            <p class="mt-1 text-lg font-semibold text-slate-900">
                                {{ currentUserName }}
                            </p>
                            <p class="text-xs text-slate-500">Creating this assessment on behalf of the signed-in teacher.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <Link
                            :href="route('assessments.index')"
                            class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-white"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton type="submit" :disabled="!canSubmit">
                            {{ form.processing ? 'Saving…' : 'Save assessment' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </MainAuthLayout>
</template>
