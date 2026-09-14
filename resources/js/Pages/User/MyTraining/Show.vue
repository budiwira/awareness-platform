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

const isCompleted = computed(() => props.assignment.status === 'completed');

const statusBadgeClass = computed(() => {
    if (isCompleted.value) return 'badge-ok';
    return 'badge-warn';
});

const statusLabel = computed(() => {
    if (isCompleted.value) return 'Selesai';
    if (props.assignment.status === 'in_progress') return 'Sedang Dikerjakan';
    return 'Ditugaskan';
});

const stage = computed(() => {
    if (props.pretestQuiz && !props.pretestAttempt) return 'pretest';
    if (props.posttestAttempt) return 'result';
    return 'materi';
});

const pretestUrl = computed(() => route('user.training.quiz', { assignment: props.assignment.id, purpose: 'pretest' }));
const posttestUrl = computed(() => route('user.training.quiz', { assignment: props.assignment.id, purpose: 'posttest' }));
const completing = ref(false);

const markComplete = () => {
    if (isCompleted.value || completing.value) return;
    if (confirm('Tandai modul ini sebagai selesai?')) {
        completing.value = true;
        router.patch(route('user.training.complete', props.assignment.id), {}, {
            preserveScroll: true,
            onFinish: () => { completing.value = false; },
        });
    }
};
</script>

<template>
    <Head :title="module.title" />

    <AppLayout :title="module.title">
        <div class="mb-6">
            <Link :href="route('user.training.index')" class="text-sm hover:underline" style="color: var(--t-link);">
                &larr; Kembali ke Daftar Training
            </Link>
        </div>

        <div class="card p-8">
            <div class="mb-6">
                <div class="flex items-center justify-between gap-4">
                    <h1 class="font-display text-2xl font-bold t-ink">{{ module.title }}</h1>
                    <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusBadgeClass">{{ statusLabel }}</span>
                </div>
                <p class="text-sm t-muted mt-2">{{ module.description }}</p>
                <p class="text-xs t-muted mt-1">{{ module.duration_minutes }} menit</p>
            </div>

            <div v-if="stage === 'pretest'" class="rounded-lg border b-line bg-app p-6 text-center">
                <h2 class="text-lg font-semibold t-ink mb-2">Pretest Tersedia</h2>
                <p class="text-sm t-muted mb-4">Ukur pengetahuan awalmu sebelum membaca materi. Skor pretest tidak mempengaruhi nilai akhir.</p>
                <Link :href="pretestUrl" class="btn btn-primary">Mulai Pretest</Link>
            </div>

            <div v-else-if="stage === 'materi'" class="space-y-6">
                <div class="prose max-w-none" v-html="module.content_html"></div>

                <div class="border-t b-line pt-6 flex flex-col gap-3">
                    <Link v-if="posttestQuiz && !isCompleted" :href="posttestUrl" class="btn btn-primary w-full">Lanjut ke Posttest</Link>
                    <button v-else-if="!posttestQuiz && !isCompleted" class="btn btn-primary w-full" :disabled="completing" @click="markComplete">{{ completing ? 'Memproses...' : 'Tandai Selesai' }}</button>
                    <span v-else class="text-sm t-muted text-center">Modul ini sudah kamu selesaikan.</span>
                </div>
            </div>

            <div v-else class="rounded-lg border b-line bg-app p-6 text-center">
                <h2 class="text-lg font-semibold t-ink mb-2">Modul Selesai</h2>
                <p class="text-sm t-muted mb-4">Kamu sudah menyelesaikan posttest modul ini.</p>
                <Link :href="route('user.quiz.result', posttestAttempt.id)" class="btn btn-primary">Lihat Hasil & Learning Gain</Link>
            </div>
        </div>
    </AppLayout>
</template>
