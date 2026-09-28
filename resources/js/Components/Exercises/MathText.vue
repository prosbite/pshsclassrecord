<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    content: {
        type: String,
        default: '',
    },
    tag: {
        type: String,
        default: 'span',
    },
});

const el = ref(null);
let renderMathInElement = null;
let loading = null;

const loadRenderer = () => {
    if (renderMathInElement || loading) {
        return loading;
    }

    loading = import('katex/contrib/auto-render')
        .then((module) => {
            renderMathInElement = module.default ?? module.renderMathInElement;

            return renderMathInElement;
        })
        .catch(() => null);

    return loading;
};

const render = async () => {
    if (!el.value) {
        return;
    }

    const raw = props.content ?? '';

    // Keep the source as text so malformed input simply falls back to literal
    // text instead of markup (never bind user input with v-html).
    el.value.textContent = raw;

    if (raw.trim() === '') {
        return;
    }

    const renderer = await loadRenderer();

    if (!renderer || !el.value) {
        return;
    }

    try {
        renderer(el.value, {
            delimiters: [
                { left: '$$', right: '$$', display: true },
                { left: '\\[', right: '\\]', display: true },
                { left: '\\(', right: '\\)', display: false },
            ],
            throwOnError: false,
            trust: false,
            maxExpand: 1000,
        });
    } catch (error) {
        el.value.textContent = raw;
    }
};

onMounted(render);
watch(() => props.content, render);
</script>

<template>
    <component :is="tag" ref="el"></component>
</template>
