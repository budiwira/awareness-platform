<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseBadge } from '@/Components';

const props = defineProps({
    attempt: Object,
    assignment: Object,
    attemptContext: Object,
    actions: Object,
});

const attemptStatus = computed(() => {
    if (props.attempt.purpose === 'pretest') return { label: 'Baseline tercatat', variant: 'info' };
    if (props.attempt.expired) return { label: 'Waktu habis', variant: 'warning' };
    return props.attempt.passed ? { label: 'Lulus', variant: 'success' } : { label: 'Belum lulus', variant: 'danger' };
});
const overallStatus = computed(() => ({
    completed: { label: 'Training selesai', variant: 'success' },
    needs_remediation: { label: 'Perlu tindak lanjut', variant: 'danger' },
    cancelled: { label: 'Dibatalkan', variant: 'neutral' },
    in_progress: { label: 'Sedang berjalan', variant: 'info' },
}[props.assignment.overall_status] ?? { label: 'Sedang berjalan', variant: 'info' }));
const gainLabel = computed(() => {
    const gain = props.assignment.learning_gain;
    if (gain === null || gain === undefined) return null;
    return `${gain > 0 ? '+' : ''}${gain} pp`;
});
const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    : '-';
</script>

<template>
    <Head title="Hasil Assessment" />
    <AppLayout title="Hasil Assessment">
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="card p-6 sm:p-8 fade-in">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide t-muted">Hasil percobaan</p>
                        <h1 class="mt-2 font-display text-2xl font-bold t-ink">{{ attempt.quiz_title }}</h1>
                        <p class="mt-2 text-sm t-muted">{{ attempt.purpose === 'pretest' ? 'Pretest baseline' : `Posttest · Percobaan ${attempt.number} / ${attemptContext.attempts_maximum}` }}</p>
                    </div>
                    <BaseBadge :variant="attemptStatus.variant">{{ attemptStatus.label }}</BaseBadge>
                </div>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">{{ attempt.purpose === 'pretest' ? 'Skor baseline' : 'Skor percobaan' }}</dt><dd class="mt-1 font-display text-3xl font-bold t-ink">{{ attempt.score }}%</dd></div>
                    <div v-if="attempt.purpose === 'posttest'" class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">Passing score</dt><dd class="mt-1 font-display text-3xl font-bold t-ink">{{ attempt.passing_score }}%</dd></div>
                    <div v-if="attempt.purpose === 'posttest'" class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">Sisa percobaan</dt><dd class="mt-1 font-display text-3xl font-bold t-ink">{{ attemptContext.attempts_remaining }}</dd></div>
                </dl>
                <p v-if="attempt.purpose === 'pretest'" class="mt-5 text-sm leading-6 t-muted">Nilai ini digunakan sebagai titik awal untuk mengukur peningkatan setelah pembelajaran. Pretest bukan penentu lulus atau gagal dan tidak dapat diulang.</p>
                <p v-else-if="attempt.expired" class="mt-5 text-sm leading-6 t-muted">Percobaan berakhir karena waktu habis. Percobaan ini memakai satu kuota, tidak dianggap lulus, dan tidak memperbarui skor Posttest terbaik.</p>
                <p v-if="attemptContext.cooldown_until && !attempt.passed" class="mt-3 text-sm font-medium t-ink">Percobaan berikutnya tersedia {{ formatDateTime(attemptContext.cooldown_until) }}.</p>
            </section>

            <section v-if="attempt.purpose === 'posttest'" class="card p-6 sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide t-muted">Progress keseluruhan modul</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Hasil pembelajaran</h2></div><BaseBadge :variant="overallStatus.variant">{{ overallStatus.label }}</BaseBadge></div>
                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div v-if="assignment.has_pretest" class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">Baseline</dt><dd class="mt-1 text-2xl font-bold t-ink">{{ assignment.baseline_score ?? '-' }}{{ assignment.baseline_score !== null ? '%' : '' }}</dd></div>
                    <div v-if="assignment.has_posttest" class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">Posttest valid terbaik</dt><dd class="mt-1 text-2xl font-bold t-ink">{{ assignment.best_posttest_score ?? '-' }}{{ assignment.best_posttest_score !== null ? '%' : '' }}</dd></div>
                    <div v-if="assignment.learning_gain_available" class="rounded-xl bg-surface2 p-4"><dt class="text-xs t-muted">Learning Gain</dt><dd class="mt-1 text-2xl font-bold t-ink">{{ gainLabel }}</dd><p class="mt-1 text-xs t-muted">Peningkatan pembelajaran dalam percentage points.</p></div>
                </dl>
                <p v-if="attempt.purpose === 'posttest' && !assignment.has_pretest" class="mt-5 text-sm t-muted">Learning Gain tidak tersedia karena modul ini tidak menggunakan Pretest.</p>
                <p v-else-if="attempt.purpose === 'posttest' && assignment.has_pretest && !assignment.learning_gain_available" class="mt-5 text-sm t-muted">Learning Gain tersedia setelah ada skor Posttest valid.</p>
                <p v-if="assignment.overall_status === 'needs_remediation'" class="mt-5 text-sm font-medium t-ink">Percobaan telah habis. Hubungi admin organisasi untuk tindak lanjut.</p>
            </section>

            <div class="card flex flex-wrap justify-center gap-3 p-5">
                <Link v-if="actions.review_allowed" :href="actions.review_url" class="btn btn-secondary">Review jawaban</Link>
                <Link :href="actions.module_url" class="btn btn-primary">{{ attempt.purpose === 'pretest' ? 'Lanjutkan belajar' : 'Kembali ke Module Room' }}</Link>
                <Link :href="actions.training_url" class="btn btn-secondary">Training Saya</Link>
            </div>
        </div>
    </AppLayout>
</template>
