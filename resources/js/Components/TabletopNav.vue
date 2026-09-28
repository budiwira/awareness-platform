<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Navigasi Tabletop">
        <Link :href="route('tenant.ttx.sessions.index')" class="chip" :class="active === 'sessions' ? 'chip-active' : ''">Sessions</Link>
    </nav>
</template>
