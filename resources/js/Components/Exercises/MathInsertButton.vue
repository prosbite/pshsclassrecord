<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import MathText from '@/Components/Exercises/MathText.vue';

const show = ref(false);
const latex = ref('');
const mathliveState = ref('idle');
const mathField = ref(null);

let target = null;

const isTextLike = (element) => {
    if (!element) {
        return false;
    }

    return element.tagName === 'TEXTAREA'
        || (element.tagName === 'INPUT' && ['text', 'search', ''].includes(element.type));
};

const open = async () => {
    const active = document.activeElement;
    target = isTextLike(active) ? active : null;

    latex.value = '';
    show.value = true;

    await ensureMathlive();

    if (mathliveState.value === 'ready') {
        await nextTick();

        if (mathField.value) {
            mathField.value.value = '';
            mathField.value.focus();
        }
    }
};

const ensureMathlive = async () => {
    if (mathliveState.value === 'ready' || mathliveState.value === 'failed') {
        return;
    }

    mathliveState.value = 'loading';

    try {
        await import('mathlive');
        mathliveState.value = 'ready';
    } catch (error) {
        // Assets can fail to resolve under some Vite setups; the plain textarea
        // fallback keeps authoring unblocked.
        mathliveState.value = 'failed';
    }
};

const insertInto = (element, text) => {
    const start = element.selectionStart ?? element.value.length;
    const end = element.selectionEnd ?? element.value.length;
    const value = element.value ?? '';

    element.value = value.slice(0, start) + text + value.slice(end);
    const caret = start + text.length;

    if (typeof element.setSelectionRange === 'function') {
        element.setSelectionRange(caret, caret);
    }

    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.focus();
};

const confirm = () => {
    const trimmed = (latex.value || '').trim();

    if (trimmed && target) {
        insertInto(target, `\\(${trimmed}\\)`);
    }

    close();
};

const close = () => {
    show.value = false;
    latex.value = '';

    if (window.mathVirtualKeyboard) {
        window.mathVirtualKeyboard.hide();
    }
};

const onKeydown = (event) => {
    if (event.key === 'Escape' && show.value) {
        event.preventDefault();
        close();
    }
};

watch(show, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
});

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
        title="Insert equation"
        @mousedown.prevent
        @click="open"
    >
        √x
    </button>

    <!--
        Deliberately not the shared <Modal> (a native <dialog>): its top layer
        paints above MathLive's on-screen keyboard, which appends itself to
        document.body. A normal fixed overlay at z-70/80 sits above the app
        header (z-50) but below the keyboard (z-index 105), so its keycaps stay
        tappable.
    -->
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[70] overflow-y-auto px-4 py-6 sm:px-0">
                <div class="fixed inset-0 z-[70] bg-gray-500 opacity-75" @click="close"></div>

                <div class="relative z-[80] mx-auto mb-6 w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
                    <div class="space-y-4 p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-[0.45em] text-slate-400">Equation</p>
                                <p class="text-sm text-slate-500">Write math the way you would on paper; it is inserted inline.</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-600 transition hover:bg-slate-50"
                                    @click="close"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800 disabled:opacity-40"
                                    :disabled="mathliveState === 'loading'"
                                    @click="confirm"
                                >
                                    Insert
                                </button>
                            </div>
                        </div>

                        <math-field
                            v-if="mathliveState === 'ready'"
                            ref="mathField"
                            class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-lg text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                            @input="latex = $event.target.value"
                        ></math-field>

                        <textarea
                            v-else-if="mathliveState === 'failed'"
                            v-model="latex"
                            rows="3"
                            placeholder="x^2 + 3x"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:bg-white focus:outline-none"
                        ></textarea>

                        <p v-else class="text-sm text-slate-500">Loading equation editor…</p>

                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[10px] uppercase tracking-widest text-slate-400">Preview</p>
                            <MathText :content="latex ? '\\(' + latex + '\\)' : ''" class="text-lg text-slate-800" />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
