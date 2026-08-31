<script setup>
defineProps({
    message: { type: String, default: 'Belum ada data.' },
});
</script>

<template>
    <div class="px-6 py-10 text-center text-sm" style="color: var(--muted)">
        <div class="text-3xl mb-2">🗂️</div>
        {{ message }}
    </div>
</template>