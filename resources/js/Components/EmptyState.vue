<script setup>
defineProps({
    message: { type: String, default: 'Belum ada data.' },
});
</script>

<template>
    <div class="px-6 py-10 text-center text-sm" style="color: var(--muted)">
        <svg class="mx-auto mb-3 h-10 w-10" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="var(--brand)" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5m-15-4.5h13.5a1.5 1.5 0 0 1 1.5 1.5v10.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z" />
            <path stroke-linecap="round" d="M9.75 14.25h4.5" />
        </svg>
        <p>{{ message }}</p>
    </div>
</template>
