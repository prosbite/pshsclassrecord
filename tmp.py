from pathlib import Path
path = Path('resources/js/Components/Assessments/AssessmentSummary.vue')
text = path.read_text()
prefix, rest = text.split('<script setup>', 1)
_, suffix = rest.split('</script>', 1)
new_script = """<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

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

const selectedSection = ref(props.sectionFilter ?? 'all');

const sectionOptions = computed(() => {
    const options = props.sections.map((section) => {
        const grade = section.grade_level?.grade_level;
        const name = section.section_name || 'Section';
        const label = grade ? `${grade} · ${name}` : name;
        return { id: String(section.id), label };
    });

    return [
        { id: 'all', label: 'All sections' },
        ...options,
        { id: 'unassigned', label: 'Unassigned section' },
    ];
});

watch(
    () => sectionOptions.value.map((option) => option.id),
    (ids) => {
        if (!ids.includes(selectedSection.value)) {
            selectedSection.value = 'all';
        }
    },
    { immediate: true }
);

watch(
    () => props.sectionFilter,
    (value) => {
        const normalized = value ?? 'all';

        if (normalized !== selectedSection.value) {
            selectedSection.value = normalized;
        }
    }
);

watch(selectedSection, (value) => {
    const normalized = value || 'all';
    const currentFilter = props.sectionFilter ?? 'all';

    if (normalized === currentFilter) {
        return;
    }

    const query = normalized === 'all' ? {} : { section: normalized };

    router.visit(route('assessments.summary'), {
        method: 'get',
        data: query,
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const filteredAssessments = computed(() => {
    const selection = selectedSection.value;

    if (!selection || selection === 'all') {
        return props.assessments;
    }

    if (selection === 'unassigned') {
        return props.assessments.filter((assessment) => !assessment.section?.id);
    }

    return props.assessments.filter((assessment) => String(assessment.section?.id) === selection);
});
</script>
"""
text = prefix + new_script + suffix
path.write_text(text)
