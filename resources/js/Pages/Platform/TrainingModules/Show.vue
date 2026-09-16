<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichContent from '@/Components/RichContent.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({ module: Object, stats: Object });
const processing = ref(null);

const publish = () => {
    if (processing.value) return;
    processing.value = 'publish';
    router.post(route('platform.modules.publish', props.module.id), {}, { onFinish: () => (processing.value = null) });
};

const archive = () => {
    if (confirm('Arsipkan modul ini? Modul tidak akan terlihat di tenant.')) {
        processing.value = 'archive';
        router.post(route('platform.modules.archive', props.module.id), {}, { onFinish: () => (processing.value = null) });
    }
};

const statusBadge = computed(() => {
    const map = {
        draft: 'neutral',
        published: 'success',
        archived: 'warning',
    };
    return map[props.module.status] || 'neutral';
});

const statusLabel = computed(() => {
    const map = { draft: 'Draft', published: 'Published', archived: 'Archived' };
    return map[props.module.status] || props.module.status;
});

const completionRate = computed(() => {
    if (props.stats.assignments_count === 0) return 0;
    return Math.round((props.stats.completed_count / props.stats.assignments_count) * 100);
});
</script>

<template>
    <Head :title="module.title" />

    <AppLayout :title="module.title">
        <!-- Header -->
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:justify-between">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h2 class="font-display text-2xl font-bold t-ink">{{ module.title }}</h2>
                    <BaseBadge :variant="statusBadge">
                        {{ statusLabel }}
                    </BaseBadge>
                </div>
                <p class="text-sm t-muted">{{ module.description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('platform.modules.edit', module.id)" class="btn btn-secondary">
                    Edit
                </Link>
                <BaseButton v-if="module.status === 'draft'" :loading="processing === 'publish'" @click="publish">{{ processing === 'publish' ? 'Memproses...' : 'Publish' }}</BaseButton>
                <BaseButton v-if="module.status === 'published'" variant="danger" :loading="processing === 'archive'" @click="archive">{{ processing === 'archive' ? 'Memproses...' : 'Archive' }}</BaseButton>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Statistik cards -->
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Ditugaskan</div>
                <div class="text-3xl font-display font-bold t-ink">{{ stats.assignments_count }}</div>
                <div class="text-xs t-muted mt-1">kali</div>
            </div>
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Completion Rate</div>
                <div class="text-3xl font-display font-bold chip-brand">{{ completionRate }}%</div>
                <div class="text-xs t-muted mt-1">{{ stats.completed_count }} / {{ stats.assignments_count }} selesai</div>
            </div>
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Rata-rata Skor</div>
                <div class="text-3xl font-display font-bold" style="color: var(--brand)">{{ stats.avg_score }}</div>
                <div class="text-xs t-muted mt-1">dari kuis</div>
            </div>
        </div>

        <!-- Materi -->
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-lg font-bold t-ink">Materi</h3>
                <span class="inline-flex items-center gap-1.5 text-sm t-muted">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ module.duration_minutes }} menit
                </span>
            </div>
            <RichContent :html="module.content_html" />
        </div>

        <!-- Kuis -->
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-lg font-bold t-ink">Evaluasi</h3>
                <Link
                    v-if="module.quiz"
                    :href="route('platform.quizzes.show', module.quiz.id)"
                    class="btn btn-secondary text-sm"
                >
                    Kelola Kuis
                </Link>
                <span v-else class="text-sm t-muted">Belum ada kuis</span>
            </div>
            <div v-if="module.quiz" class="bg-app rounded-lg p-4">
                <div class="text-sm t-ink">
                    <strong>{{ module.quiz.questions?.length || 0 }}</strong> pertanyaan tersedia
                </div>
            </div>
            <BaseAlert v-else variant="warning">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Modul ini belum memiliki kuis. Buat kuis di halaman <strong>Quizzes</strong> dan pilih modul ini.
            </BaseAlert>
        </div>
    </AppLayout>
</template>
