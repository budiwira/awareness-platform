<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    hint: { type: String, default: null },
    accent: { type: String, default: 'border-indigo-500' },
});
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm p-6 border-l-4" :class="accent">
        <div class="text-sm text-gray-500">{{ label }}</div>
        <div class="text-2xl font-bold text-gray-900 mt-1">{{ value }}</div>
        <div v-if="hint" class="text-xs text-gray-400 mt-1">{{ hint }}</div>
    </div>
</template>