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
            <Link :href="route('user.training.index')" class="inline-flex items-center gap-2 text-sm t-muted transition-colors hover:t-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="color: var(--t-link);">
                <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 12H5m6 6-6-6 6-6" />
                </svg>
                Kembali ke Training Saya
            </Link>
        </div>

        <div class="card p-6 sm:p-8 fade-in">
            <div class="mb-8 border-b b-line pb-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide t-muted mb-2">Detail modul</p>
                        <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">{{ module.title }}</h1>
                        <p v-if="module.description" class="mt-3 max-w-3xl text-sm leading-6 t-muted">{{ module.description }}</p>
                    </div>
                    <span class="self-start rounded-full px-3 py-1 text-xs font-medium" :class="statusBadgeClass">{{ statusLabel }}</span>
                </div>
                <div class="mt-4 inline-flex items-center gap-2 text-sm t-muted">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                    </svg>
                    Estimasi {{ module.duration_minutes }} menit
                </div>
            </div>

            <div v-if="stage === 'pretest'" class="rounded-xl border b-line bg-app p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide t-muted mb-2">Tahap 1 dari 4</p>
                <h2 class="font-display text-xl font-semibold t-ink mb-2">Mulai dengan pretest</h2>
                <p class="max-w-2xl text-sm leading-6 t-muted mb-5">Ukur pemahaman awal Anda sebelum mempelajari materi. Hasil ini menjadi titik pembanding dan tidak memengaruhi nilai akhir.</p>
                <Link :href="pretestUrl" class="btn btn-primary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2">Mulai Pretest</Link>
            </div>

            <div v-else-if="stage === 'materi'" class="space-y-6">
                <div class="flex items-center gap-3 rounded-xl border b-line bg-app px-4 py-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg badge-ok">
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-medium t-ink">Materi pembelajaran</p>
                        <p class="text-xs t-muted">Baca materi berikut sebelum melanjutkan ke tahap berikutnya.</p>
                    </div>
                </div>
                <div class="prose max-w-none" v-html="module.content_html"></div>

                <div class="border-t b-line pt-6 flex flex-col gap-3">
                    <template v-if="posttestQuiz && !isCompleted">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide t-muted mb-1">Tahap 3 dari 4</p>
                            <p class="text-sm font-medium t-ink">Uji pemahaman Anda melalui posttest.</p>
                        </div>
                        <Link :href="posttestUrl" class="btn btn-primary w-full justify-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2">Lanjut ke Posttest</Link>
                    </template>
                    <button v-else-if="!posttestQuiz && !isCompleted" class="btn btn-primary w-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" :disabled="completing" @click="markComplete">{{ completing ? 'Memproses...' : 'Tandai Selesai' }}</button>
                    <span v-else class="text-center text-sm t-muted">Materi ini sudah selesai dipelajari.</span>
                </div>
            </div>

            <div v-else class="rounded-xl border b-line bg-app p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide t-muted mb-2">Tahap 4 dari 4</p>
                <h2 class="font-display text-xl font-semibold t-ink mb-2">Modul selesai</h2>
                <p class="max-w-2xl text-sm leading-6 t-muted mb-5">Anda telah menyelesaikan posttest. Tinjau hasilnya untuk melihat perkembangan pemahaman Anda.</p>
                <Link :href="route('user.quiz.result', posttestAttempt.id)" class="btn btn-primary transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2">Lihat Hasil dan Learning Gain</Link>
            </div>
        </div>
    </AppLayout>
</template>
