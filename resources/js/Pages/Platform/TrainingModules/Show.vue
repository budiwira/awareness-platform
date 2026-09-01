<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ module: Object, stats: Object });

const publish = () => {
    router.post(route('platform.modules.publish', props.module.id));
};

const archive = () => {
    if (confirm('Arsipkan modul ini? Modul tidak akan terlihat di tenant.')) {
        router.post(route('platform.modules.archive', props.module.id));
    }
};

const statusBadge = computed(() => {
    const map = {
        draft: 'bg-surface2 t-ink',
        published: 'bg-emerald-100 badge-ok',
        archived: 'bg-amber-100 badge-warn',
    };
    return map[props.module.status] || 'bg-surface2 t-ink';
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
        <div class="flex items-start justify-between mb-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h2 class="font-display text-2xl font-bold t-ink">{{ module.title }}</h2>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusBadge">
                        {{ statusLabel }}
                    </span>
                </div>
                <p class="text-sm t-muted">{{ module.description }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('platform.modules.edit', module.id)" class="btn btn-secondary">
                    Edit
                </Link>
                <button v-if="module.status === 'draft'" @click="publish" class="btn btn-primary">
                    Publish
                </button>
                <button v-if="module.status === 'published'" @click="archive" class="btn" style="background: #f59e0b; color: white;">
                    Archive
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Statistik cards -->
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Ditugaskan</div>
                <div class="text-3xl font-display font-bold t-ink">{{ stats.assignments_count }}</div>
                <div class="text-xs text-gray-400 mt-1">kali</div>
            </div>
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Completion Rate</div>
                <div class="text-3xl font-display font-bold text-teal-600">{{ completionRate }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ stats.completed_count }} / {{ stats.assignments_count }} selesai</div>
            </div>
            <div class="card p-6">
                <div class="text-sm t-muted mb-1">Rata-rata Skor</div>
                <div class="text-3xl font-display font-bold text-indigo-600">{{ stats.avg_score }}</div>
                <div class="text-xs text-gray-400 mt-1">dari kuis</div>
            </div>
        </div>

        <!-- Materi -->
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-lg font-bold t-ink">Materi</h3>
                <span class="text-sm t-muted">⏱ {{ module.duration_minutes }} menit</span>
            </div>
            <div class="prose prose-sm max-w-none t-ink" v-html="module.content"></div>
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
                <span v-else class="text-sm text-gray-400">Belum ada kuis</span>
            </div>
            <div v-if="module.quiz" class="bg-app rounded-lg p-4">
                <div class="text-sm t-ink">
                    <strong>{{ module.quiz.questions?.length || 0 }}</strong> pertanyaan tersedia
                </div>
            </div>
            <div v-else class="badge-warn border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Modul ini belum memiliki kuis. Buat kuis di halaman <strong>Quizzes</strong> dan pilih modul ini.
            </div>
        </div>
    </AppLayout>
</template>
