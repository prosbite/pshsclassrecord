<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

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

const fileInput = ref(null);
const fileName = ref('');
const tableHeaders = ref([]);
const tableRows = ref([]);
const errorMessage = ref('');
const isParsing = ref(false);
const selectedSection = ref('');
const selectedQuarter = ref('');
const saveStatus = ref('');

const form = useForm({
    section_id: '',
    quarter_id: '',
    school_year_id: props.schoolYear?.id ?? null,
    assessment: [],
});

const triggerUpload = () => {
    fileInput.value?.click();
};

const parseCsvLine = (line) => {
    const cells = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            const nextChar = line[i + 1];
            if (inQuotes && nextChar === '"') {
                current += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }

            continue;
        }

        if (char === ',' && !inQuotes) {
            cells.push(current);
            current = '';
            continue;
        }

        current += char;
    }

    cells.push(current);

    return cells.map((value) => value.trim());
};

const parseCsv = (text) => {
    const lines = text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

    if (!lines.length) {
        throw new Error('The CSV file is empty.');
    }

    const headers = parseCsvLine(lines[0]);

    if (!headers.length) {
        throw new Error('Unable to detect headers in the CSV file.');
    }

    const rows = lines
        .slice(1)
        .map((line) => {
            const cells = parseCsvLine(line);

            if (cells.length < headers.length) {
                return [...cells, ...Array(headers.length - cells.length).fill('')];
            }

            if (cells.length > headers.length) {
                return cells.slice(0, headers.length);
            }

            return cells;
        });

    return { headers, rows };
};

watch(
    () => props.schoolYear?.id,
    (value) => {
        form.school_year_id = value ?? null;
    },
    { immediate: true },
);

const sectionOptions = computed(() => {
    return [
        { id: '', label: 'Select section' },
        ...props.sections.map((section) => {
            const gradeLabel = section.grade_level?.grade_level
                ? `${section.grade_level.grade_level} · `
                : '';
            const label = `${gradeLabel}${section.section_name || 'Unclassified section'}`;
            return { id: String(section.id), label };
        }),
    ];
});

const quarterOptions = computed(() => {
    return [
        { id: '', label: 'Select quarter' },
        ...props.quarters.map((quarter) => ({
            id: String(quarter.id),
            label: quarter.name || `Quarter ${quarter.quarter ?? '—'}`,
        })),
    ];
});

const selectedSectionLabel = computed(() => {
    if (!selectedSection.value) {
        return null;
    }

    return sectionOptions.value.find((option) => option.id === selectedSection.value)?.label ?? null;
});

const selectedQuarterLabel = computed(() => {
    if (!selectedQuarter.value) {
        return null;
    }

    return quarterOptions.value.find((option) => option.id === selectedQuarter.value)?.label ?? null;
});

const handleSave = () => {
    saveStatus.value = '';

    if (!tableHeaders.value.length || !tableRows.value.length) {
        saveStatus.value = 'Upload a CSV file before saving.';
        return;
    }

    if (!selectedSection.value) {
        saveStatus.value = 'Please choose a section.';
        return;
    }

    if (!selectedQuarter.value) {
        saveStatus.value = 'Please choose a quarter.';
        return;
    }

    form.section_id = selectedSection.value;
    form.quarter_id = selectedQuarter.value;
    form.assessment = {
        headers: tableHeaders.value,
        rows: tableRows.value,
    };

    form.post(route('quarterly-assessments.store'), {
        onStart: () => {
            saveStatus.value = '';
        },
        onSuccess: () => {
            saveStatus.value = 'Quarterly assessment saved.';
        },
        onError: () => {
            saveStatus.value = 'Unable to save. Check the highlighted fields.';
        },
    });
};

const handleFileChange = async (event) => {
    const file = event.target?.files?.[0];

    if (!file) {
        return;
    }

    errorMessage.value = '';
    tableHeaders.value = [];
    tableRows.value = [];
    fileName.value = file.name;
    isParsing.value = true;

    try {
        const content = await file.text();
        const snapshot = parseCsv(content);
        tableHeaders.value = snapshot.headers;
        tableRows.value = snapshot.rows;
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Unable to parse the CSV file.';
    } finally {
        isParsing.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};
</script>

<template>
    <div class="rounded-3xl bg-white p-6 shadow-xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Quarterly assessments</p>
                <h1 class="text-2xl font-semibold text-slate-900">CSV upload</h1>
                <p class="text-sm text-slate-500">
                    Upload a quarter breakdown in CSV format to render the full table of columns and scores.
                </p>
            </div>
            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="flex items-center gap-3">
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".csv"
                        class="sr-only"
                        @change="handleFileChange"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2 text-[0.65rem] font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:opacity-50"
                        @click="triggerUpload"
                        :disabled="isParsing"
                    >
                        {{ isParsing ? 'Parsing…' : 'Upload CSV' }}
                    </button>
                    <span v-if="fileName" class="text-xs text-slate-500">File: {{ fileName }}</span>
                </div>
                <p class="text-xs text-slate-400">
                    Expected format: first row of headers, subsequent rows of comma-separated values.
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <label class="text-[0.6rem] font-semibold uppercase tracking-[0.35em] text-slate-500">
                Section
                <select
                    v-model="selectedSection"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-[0.75rem] font-semibold uppercase tracking-[0.35em] text-slate-600 transition hover:border-slate-300 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    <option
                        v-for="option in sectionOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <label class="text-[0.6rem] font-semibold uppercase tracking-[0.35em] text-slate-500">
                Quarter
                <select
                    v-model="selectedQuarter"
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-[0.75rem] font-semibold uppercase tracking-[0.35em] text-slate-600 transition hover:border-slate-300 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300"
                >
                    <option
                        v-for="option in quarterOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
        </div>
        <div class="mt-1 space-y-1 text-[0.65rem] text-rose-600">
            <p v-if="form.errors.section_id">{{ form.errors.section_id }}</p>
            <p v-if="form.errors.quarter_id">{{ form.errors.quarter_id }}</p>
        </div>

        <p v-if="errorMessage" class="mt-4 text-sm text-rose-600" role="alert">
            {{ errorMessage }}
        </p>

        <p v-else-if="isParsing" class="mt-4 text-sm text-slate-500">Parsing file…</p>

        <div v-else-if="tableHeaders.length" class="mt-6 overflow-x-auto rounded-2xl border border-slate-100 bg-slate-50/70 shadow-inner">
            <table class="min-w-full table-fixed text-[0.7rem] text-slate-600">
                <thead class="bg-white text-[0.6rem] uppercase tracking-[0.35em] text-slate-500">
                    <tr>
                        <th
                            v-for="header in tableHeaders"
                            :key="header"
                            class="px-3 py-3 text-left first:rounded-tl-2xl last:rounded-tr-2xl"
                        >
                            {{ header }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-if="!tableRows.length" class="bg-white">
                        <td
                            :colspan="tableHeaders.length"
                            class="px-4 py-6 text-center text-sm text-slate-400"
                        >
                            The file does not contain any data rows.
                        </td>
                    </tr>
                    <tr v-for="(row, rowIndex) in tableRows" :key="rowIndex" class="hover:bg-slate-50">
                        <td
                            v-for="(cell, cellIndex) in row"
                            :key="`${rowIndex}-${cellIndex}`"
                            class="max-w-[220px] px-3 py-2 text-[11px] font-medium text-slate-700"
                        >
                            {{ cell || '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="tableHeaders.length"
            class="mt-6 flex flex-col gap-3 rounded-2xl border border-dashed border-slate-200 bg-white/90 p-4 text-right shadow-sm"
        >
            <p class="text-xs text-slate-500">
                {{ selectedSectionLabel ? `Section: ${selectedSectionLabel}` : 'No section selected.' }}
                &bull;
                {{ selectedQuarterLabel ? `Quarter: ${selectedQuarterLabel}` : 'No quarter selected.' }}
            </p>
            <button
                type="button"
                class="self-end rounded-full border border-slate-200 bg-white px-6 py-2 text-[0.65rem] font-semibold uppercase tracking-[0.4em] text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50"
                @click="handleSave"
                :disabled="isParsing || form.processing"
            >
                {{ form.processing ? 'Saving…' : 'Save quarterly breakdown' }}
            </button>
            <p v-if="saveStatus" class="text-[0.75rem] text-slate-500">{{ saveStatus }}</p>
            <p v-if="form.errors.assessment" class="text-[0.75rem] text-rose-500">{{ form.errors.assessment }}</p>
        </div>

        <p v-else class="mt-6 text-sm text-slate-500">
            Upload a CSV file to see its headers become columns and each row rendered directly below.
        </p>
    </div>
</template>
