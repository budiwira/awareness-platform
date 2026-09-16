<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    active: { type: String, default: '' },
});
</script>

<template>
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Navigasi Tabletop">
        <Link :href="route('tenant.ttx.exercises.index')" class="chip" :class="active === 'exercises' ? 'chip-active' : ''">Latihan</Link>
        <Link :href="route('tenant.ttx.index') + '#playbooks'" class="chip" :class="active === 'playbooks' ? 'chip-active' : ''">Playbooks</Link>
        <Link :href="route('tenant.ttx.index') + '#runbooks'" class="chip" :class="active === 'runbooks' ? 'chip-active' : ''">Runbooks</Link>
    </nav>
</template>
