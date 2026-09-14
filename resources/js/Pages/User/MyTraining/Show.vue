<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    assignment: Object,
    module: Object,
    pretestQuiz: Object,
    posttestQuiz: Object,
    pretestAttempt: Object,
    posttestAttempt: Object,
});

const isCompleted = ref(props.assignment.status === 'completed');

const statusBadgeClass = computed(() => {
    if (isCompleted.value) return 'badge-ok';
    if (props.assignment.status === 'in_progress') return 'bg-blue-100 text-blue-700';
    return 'bg-yellow-100 text-yellow-700';
});

const statusLabel = computed(() => {
    if (isCompleted.value) return 'Selesai';
    if (props.assignment.status === 'in_progress') return 'Sedang Dikerjakan';
    return 'Ditugaskan';
});

// State machine
const stage = computed(() => {
    if (props.pretestQuiz && !props.pretestAttempt) return 'pretest';
    if (props.posttestQuiz && !props.posttestAttempt) return 'posttest';
    if (props.posttestAttempt) return 'result';
    return 'materi';
});
</script>

<template>
    <Head :title="module.title" />

    <AppLayout :title="module.title">
        <div class="mb-6">
            <Link :href="route('user.training.index')" class="text-sm hover:underline transition-colors" style="color: var(--t-link);">
                &larr; Kembali ke Daftar Training
            </Link>
        </div>

        <div class="card p-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="font-display text-2xl font-bold mb-2">{{ module.title }}</h1>
                <p class="text-sm" style="color: var(--t-muted);">{{ module.description }}</p>
                <div class="flex items-center gap-4 mt-4">
                    <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusBadgeClass">
                        {{ statusLabel }}
                    </span>
                    <span class="text-xs" style="color: var(--t-muted);">
                        {{ module.duration_minutes }} menit
                    </span>
                </div>
            </div>

            <!-- Stage: Pretest Required -->
            <div v-if="stage === 'pretest'" class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--info);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h2 class="text-lg font-semibold mb-2" style="color: var(--t-ink);">Pretest Tersedia</h2>
                <p class="text-sm mb-4" style="color: var(--t-muted);">
                    Kerjakan pretest untuk mengukur pengetahuan awal Anda sebelum memulai materi.
                </p>
                <Link :href="route('user.training.quiz', assignment.id)" class="btn btn-primary">
                    Mulai Pretest
                </Link>
            </div>

            <!-- Stage: Materi -->
            <div v-else-if="stage === 'materi'" class="space-y-6">
                <div class="prose max-w-none" v-html="module.content_html"></div>

                <div v-if="posttestQuiz" class="border-t pt-6">
                    <Link :href="route('user.training.quiz', assignment.id)" class="btn btn-primary w-full">
                        Lanjut ke Posttest
                    </Link>
                </div>
            </div>

            <!-- Stage: Posttest Required -->
            <div v-else-if="stage === 'posttest'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--warning);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h2 class="text-lg font-semibold mb-2" style="color: var(--t-ink);">Posttest Tersedia</h2>
                <p class="text-sm mb-4" style="color: var(--t-muted);">
                    Kerjakan posttest untuk mengukur pemahaman Anda setelah membaca materi.
                </p>
                <Link :href="route('user.training.quiz', assignment.id)" class="btn btn-primary">
                    Mulai Posttest
                </Link>
            </div>

            <!-- Stage: Result -->
            <div v-else-if="stage === 'result'" class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--success);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-lg font-semibold mb-2" style="color: var(--t-ink);">Modul Selesai</h2>
                <p class="text-sm mb-4" style="color: var(--t-muted);">
                    Anda telah menyelesaikan posttest untuk modul ini.
                </p>
                <Link :href="route('user.quiz.result', posttestAttempt.id)" class="btn btn-primary">
                    Lihat Hasil
                </Link>
            </div>
        </div>
    </AppLayout>
</template>