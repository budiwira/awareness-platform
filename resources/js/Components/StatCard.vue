<script setup>
defineProps({
    label: String,
    value: [String, Number],
    accent: { type: String, default: 't-ink' },
    border: { type: String, default: '' },
});
</script>

<template>
    <div class="card p-5" :class="border">
        <div class="text-xs uppercase tracking-wide" style="color: var(--muted)">{{ label }}</div>
        <div class="text-2xl font-bold mt-1" :class="accent">{{ value }}</div>
    </div>
</template>