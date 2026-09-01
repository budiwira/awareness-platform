<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ cases: Array });

const start = (id) => router.post(route('user.cases.start', id));

const difficultyBadge = (d) => ({
    beginner: 'badge-ok',
    intermediate: 'bg-yellow-100 text-yellow-700',
    advanced: 'bg-red-100 text-red-700',
}[d] ?? 'bg-surface2 t-ink');
</script>

<template>
    <Head title="Case Studies" />

    <AppLayout title="Case Studies">
        <p class="text-sm t-muted mb-6">
            Latihan tabletop: baca skenario insiden dan ambil keputusan terbaik di tiap titik.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="c in cases" :key="c.id" class="card p-6 flex flex-col">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold t-ink">{{ c.title }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="difficultyBadge(c.difficulty)">
                        {{ c.difficulty }}
                    </span>
                </div>
                <p class="text-sm t-muted mb-4 flex-1">{{ c.description }}</p>
                <div class="text-xs t-muted mb-4">{{ c.scenes_count }} scene · {{ c.duration_minutes }} menit</div>

                <div class="flex items-center justify-between">
                    <span
                        v-if="c.participation"
                        class="text-xs font-medium"
                        :class="c.participation.status === 'completed' ? 'badge-ok' : 'text-blue-600'"
                    >
                        {{ c.participation.status === 'completed' ? 'Selesai · Skor ' + c.participation.score : 'Sedang dikerjakan' }}
                    </span>
                    <span v-else class="text-xs t-muted">Belum dimulai</span>

                    <button
                        v-if="!c.participation"
                        @click="start(c.id)"
                        class="btn btn-primary"
                    >
                        Mulai
                    </button>
                    <button
                        v-else-if="c.participation.status !== 'completed'"
                        @click="$inertia.visit(route('user.cases.run', c.participation.id))"
                        class="btn btn-primary"
                    >
                        Lanjutkan
                    </button>
                    <button
                        v-else
                        @click="$inertia.visit(route('user.cases.result', c.participation.id))"
                        class="btn btn-secondary"
                    >
                        Lihat Hasil
                    </button>
                </div>
            </div>
            <div v-if="cases.length === 0" class="col-span-2 card p-8 text-center t-muted text-sm">
                Belum ada case study aktif.
            </div>
        </div>
    </AppLayout>
</template>