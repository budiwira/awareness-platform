<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ case_title: String, score: Number, breakdown: Array });

const qualityBadge = (q) => ({
    best: 'badge-ok',
    acceptable: 'bg-yellow-100 text-yellow-700',
    poor: 'bg-red-100 text-red-700',
}[q] ?? 'bg-surface2 t-ink');
</script>

<template>
    <Head :title="'Hasil: ' + case_title" />

    <AppLayout :title="'Hasil: ' + case_title">
        <div class="max-w-3xl mx-auto">
            <div class="card p-8 text-center mb-6">
                <div
                    class="mx-auto w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold mb-3"
                    :class="score >= 70 ? 'badge-ok' : 'bg-red-100 text-red-700'"
                >
                    {{ score }}
                </div>
                <p class="text-sm t-muted">Skor keputusan Anda</p>
            </div>

            <div class="space-y-4 mb-8">
                <div v-for="(row, i) in breakdown" :key="i" class="card p-6">
                    <div class="font-medium t-ink mb-3">{{ i + 1 }}. {{ row.situation }}</div>

                    <div v-if="row.chosen" class="text-sm border b-line rounded-lg p-3 mb-2">
                        <div class="flex items-center justify-between">
                            <span class="t-ink">Pilihan Anda: {{ row.chosen.text }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase" :class="qualityBadge(row.chosen.quality)">
                                {{ row.chosen.quality }}
                            </span>
                        </div>
                        <p class="text-xs t-muted mt-1">{{ row.chosen.feedback }}</p>
                    </div>

                    <div v-if="row.chosen?.quality !== 'best' && row.best" class="text-xs badge-ok">
                        Keputusan terbaik: {{ row.best }}
                    </div>
                </div>
            </div>

            <div class="flex justify-center">
                <Link :href="route('user.cases.index')" class="btn btn-secondary">
                    Ke Daftar Case
                </Link>
            </div>
        </div>
    </AppLayout>
</template>