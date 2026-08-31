<script setup>
defineProps({
    value: { type: Number, default: 0 },
    size: { type: Number, default: 140 },
    label: { type: String, default: 'Awareness Score' },
});

const color = (v) => (v >= 70 ? '#0f766e' : v >= 40 ? '#f59e0b' : '#ef4444');
</script>

<template>
    <div class="flex flex-col items-center">
        <svg :width="size" :height="size" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="52" fill="none" stroke="var(--line, #e2e8f0)" stroke-width="10" />
            <circle
                cx="60" cy="60" r="52" fill="none"
                :stroke="color(value)" stroke-width="10" stroke-linecap="round"
                :stroke-dasharray="2 * Math.PI * 52"
                :stroke-dashoffset="2 * Math.PI * 52 * (1 - value / 100)"
                transform="rotate(-90 60 60)"
            />
            <text x="60" y="68" text-anchor="middle" style="font-size: 30px; font-weight: 700;" :fill="'var(--ink, #0f172a)'">
                {{ value }}
            </text>
        </svg>
        <div class="text-sm mt-2" style="color: var(--muted)">{{ label }}</div>
    </div>
</template>