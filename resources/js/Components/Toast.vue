<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref('success');
let timer = null;

watch(
    () => page.props.flash,
    (f) => {
        const msg = f?.success || f?.error;
        if (!msg) return;
        message.value = msg;
        type.value = f.success ? 'success' : 'error';
        show.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (show.value = false), 3500);
    },
    { immediate: true, deep: true }
);
</script>

<template>
    <Transition name="toast">
        <div v-if="show" class="toast" :class="type === 'success' ? 'toast-success' : 'toast-error'">
            <span v-if="type === 'success'">✓</span>
            <span v-else>⚠</span>
            {{ message }}
        </div>
    </Transition>
</template>

<style scoped>
.toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 60;
    display: flex;
    gap: .5rem;
    align-items: center;
    padding: .75rem 1.25rem;
    border-radius: .75rem;
    color: var(--surface);
    font-size: .875rem;
    font-weight: 600;
    box-shadow: 0 10px 30px rgb(0 0 0 / .18);
}
.toast-success { background: var(--ok); }
.toast-error { background: #e11d48; }
.toast-enter-active, .toast-leave-active { transition: all .25s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>