<script setup>
defineProps({
    label: String,
    value: [String, Number],
    accent: { type: String, default: 'text-gray-900' },
    border: { type: String, default: '' },
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm p-5" :class="border">
        <div class="text-xs text-gray-500 uppercase tracking-wide">{{ label }}</div>
        <div class="text-2xl font-bold mt-1" :class="accent">{{ value }}</div>
    </div>
</template>