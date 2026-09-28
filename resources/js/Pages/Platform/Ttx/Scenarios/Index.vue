<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ scenarios: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Tabletop Scenarios" />
    <AppLayout title="Tabletop Scenarios">
        <div class="fade-in space-y-6">
            <header class="card p-6 sm:p-8">
                <p class="text-sm font-semibold uppercase tracking-wide" style="color: var(--brand)">Scenario Catalog</p>
                <h1 class="font-display mt-2 text-2xl font-bold t-ink">Template skenario dan konten inject</h1>
                <p class="mt-2 max-w-3xl text-sm t-muted">Katalog sumber untuk session Tabletop. Pengelolaan tenant tetap menggunakan snapshot runtime yang aman.</p>
            </header>
            <div v-if="scenarios.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="scenario in scenarios" :key="scenario.id" class="card p-5 transition hover:shadow-md">
                    <h2 class="font-display text-lg font-semibold t-ink">{{ scenario.title }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm t-muted">{{ scenario.scenario }}</p>
                    <p class="mt-4 text-sm font-medium t-ink">{{ scenario.inject_count }} inject</p>
                </article>
            </div>
            <div v-else class="card"><EmptyState title="Belum ada skenario" message="Katalog skenario Tabletop masih kosong." /></div>
        </div>
    </AppLayout>
</template>
