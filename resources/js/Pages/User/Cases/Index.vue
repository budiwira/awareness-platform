<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ cases: Array });
const processing = ref(null);

const start = (id) => {
    if (processing.value) return;
    processing.value = id;
    router.post(route('user.cases.start', id), {}, { onFinish: () => (processing.value = null) });
};

const difficultyBadge = (difficulty) => ({ beginner: 'success', intermediate: 'warning', advanced: 'danger' }[difficulty] ?? 'neutral');
</script>

<template>
    <Head title="Case Studies" />
    <AppLayout title="Case Studies">
        <p class="mb-6 text-sm t-muted">Latihan pengambilan keputusan: baca skenario insiden dan pilih respons terbaik di setiap situasi.</p>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <article v-for="c in cases" :key="c.id" class="card flex flex-col p-5 sm:p-6">
                <div class="mb-2 flex items-start justify-between gap-3">
                    <h3 class="font-semibold t-ink">{{ c.title }}</h3>
                    <BaseBadge :variant="difficultyBadge(c.difficulty)">{{ c.difficulty }}</BaseBadge>
                </div>
                <p class="mb-4 flex-1 text-sm t-muted">{{ c.description }}</p>
                <div class="mb-4 text-xs t-muted">{{ c.scenes_count }} scene · {{ c.duration_minutes }} menit</div>
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <BaseBadge v-if="c.participation" :variant="c.participation.status === 'completed' ? 'success' : 'info'">
                        {{ c.participation.status === 'completed' ? `Selesai · Skor ${c.participation.score}` : 'Sedang dikerjakan' }}
                    </BaseBadge>
                    <span v-else class="text-xs t-muted">Belum dimulai</span>
                    <BaseButton v-if="!c.participation" size="sm" :loading="processing === c.id" :disabled="processing !== null && processing !== c.id" @click="start(c.id)">{{ processing === c.id ? 'Memproses...' : 'Mulai' }}</BaseButton>
                    <BaseButton v-else-if="c.participation.status !== 'completed'" size="sm" @click="$inertia.visit(route('user.cases.run', c.participation.id))">Lanjutkan</BaseButton>
                    <BaseButton v-else size="sm" variant="secondary" @click="$inertia.visit(route('user.cases.result', c.participation.id))">Lihat Hasil</BaseButton>
                </div>
            </article>
            <div v-if="cases.length === 0" class="card md:col-span-2"><EmptyState title="Belum ada case study" message="Case study aktif akan muncul di halaman ini." /></div>
        </div>
    </AppLayout>
</template>
