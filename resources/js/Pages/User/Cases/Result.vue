<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';

defineProps({ case_title: String, score: Number, breakdown: Array });
const qualityBadge = (quality) => ({ best: 'success', acceptable: 'warning', poor: 'danger' }[quality] ?? 'neutral');
</script>

<template>
    <Head :title="'Hasil: ' + case_title" />
    <AppLayout :title="'Hasil: ' + case_title">
        <div class="mx-auto max-w-3xl">
            <div class="card mb-6 p-6 text-center sm:p-8">
                <div class="mx-auto mb-3 flex h-24 w-24 items-center justify-center rounded-full text-2xl font-bold" :style="score >= 70 ? 'background: var(--ok-bg); color: var(--ok)' : 'background: var(--danger-bg); color: var(--danger)'">{{ score }}</div>
                <p class="text-sm t-muted">Skor keputusan Anda</p>
            </div>
            <div class="mb-8 space-y-4">
                <div v-for="(row, index) in breakdown" :key="index" class="card p-5 sm:p-6">
                    <div class="mb-3 font-medium t-ink">{{ index + 1 }}. {{ row.situation }}</div>
                    <div v-if="row.chosen" class="mb-2 rounded-lg border p-3 text-sm b-line">
                        <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="t-ink">Pilihan Anda: {{ row.chosen.text }}</span>
                            <BaseBadge size="sm" :variant="qualityBadge(row.chosen.quality)">{{ row.chosen.quality }}</BaseBadge>
                        </div>
                        <p class="mt-1 text-xs t-muted">{{ row.chosen.feedback }}</p>
                    </div>
                    <div v-if="row.chosen?.quality !== 'best' && row.best" class="rounded-lg border p-3 text-xs" style="border-color: var(--ok); background: var(--ok-bg); color: var(--ok)">Keputusan terbaik: {{ row.best }}</div>
                </div>
            </div>
            <div class="flex justify-center"><Link :href="route('user.cases.index')" class="btn btn-secondary">Ke Daftar Case</Link></div>
        </div>
    </AppLayout>
</template>
