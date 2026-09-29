<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichContent from '@/Components/RichContent.vue';
import { BaseAlert, BaseBadge } from '@/Components';

const props = defineProps({
    assignment: Object,
    module: Object,
    pretestQuiz: Object,
    posttestQuiz: Object,
    pretestAttempt: Object,
    posttestAttempt: Object,
    lifecycle: Object,
});

const stage = computed(() => props.lifecycle?.stage ?? 'configuration_unavailable');
const errors = computed(() => usePage().props.errors ?? {});
const pretestUrl = computed(() => route('user.training.quiz', { assignment: props.assignment.id, purpose: 'pretest' }));
const posttestUrl = computed(() => route('user.training.quiz', { assignment: props.assignment.id, purpose: 'posttest' }));
const completing = ref(false);
const journeySteps = computed(() => [
    ...(props.pretestQuiz ? [{ key: 'baseline', label: 'Baseline' }] : []),
    { key: 'material', label: 'Belajar' },
    ...(props.posttestQuiz ? [{ key: 'assessment', label: 'Posttest' }] : []),
    { key: 'result', label: props.posttestQuiz ? 'Hasil' : 'Selesai' },
]);
const completedKeys = computed(() => {
    const keys = [];
    if (props.pretestAttempt) keys.push('baseline');
    if (props.assignment.content_completed_at) keys.push('material');
    if (props.assignment.status === 'completed' && props.posttestQuiz) keys.push('assessment');
    if (props.assignment.status === 'completed') keys.push('result');
    return keys;
});
const currentStep = computed(() => ({
    pretest_required: 'baseline',
    material: 'material',
    posttest_available: 'assessment',
    posttest_cooldown: 'assessment',
    attempts_exhausted: 'result',
    completed: 'result',
    cancelled: 'result',
    configuration_unavailable: 'result',
}[stage.value] ?? 'material'));
const status = computed(() => {
    if (stage.value === 'attempts_exhausted') return { label: 'Perlu tindak lanjut', variant: 'danger' };
    if (stage.value === 'cancelled') return { label: 'Dibatalkan', variant: 'neutral' };
    if (stage.value === 'completed') return { label: 'Training selesai', variant: 'success' };
    if (props.lifecycle.overdue) return { label: 'Terlambat', variant: 'danger' };
    if (props.assignment.status === 'in_progress') return { label: 'Sedang dikerjakan', variant: 'info' };
    return { label: 'Ditugaskan', variant: 'warning' };
});
const learningGain = computed(() => {
    if (props.assignment.pretest_score === null || props.assignment.pretest_score === undefined
        || props.assignment.best_posttest_score === null || props.assignment.best_posttest_score === undefined) return null;
    return props.assignment.best_posttest_score - props.assignment.pretest_score;
});
const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    : '-';

const markComplete = () => {
    if (!props.lifecycle.can_complete_content || completing.value) return;
    if (confirm('Anda telah menyelesaikan materi ini?')) {
        completing.value = true;
        router.patch(route('user.training.content.complete', props.assignment.id), {}, {
            preserveScroll: true,
            onFinish: () => { completing.value = false; },
        });
    }
};
onMounted(() => {
    if (props.lifecycle?.can_start_content) {
        router.patch(route('user.training.content.start', props.assignment.id), {}, { preserveScroll: true, preserveState: true });
    }
});
</script>

<template>
    <Head :title="module.title" />
    <AppLayout :title="module.title">
        <div class="mx-auto max-w-5xl space-y-6">
            <Link :href="route('user.training.index')" class="inline-flex min-h-11 items-center gap-2 text-sm t-muted transition-colors hover:t-ink focus-visible:outline-none focus-visible:ring-2">
                <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 12H5m6 6-6-6 6-6" /></svg>
                Kembali ke Training Saya
            </Link>
            <BaseAlert v-if="errors.quiz" variant="danger" title="Tindakan tidak tersedia">{{ errors.quiz }}</BaseAlert>
            <BaseAlert v-if="lifecycle.overdue" variant="warning" title="Assignment terlambat">Deadline telah lewat, tetapi Anda tetap dapat melanjutkan sesuai tahapan yang tersedia.</BaseAlert>

            <section class="card p-6 sm:p-8 fade-in">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide t-muted">Module Room</p>
                        <h1 class="mt-2 font-display text-2xl font-bold t-ink sm:text-3xl">{{ module.title }}</h1>
                        <p v-if="module.description" class="mt-3 max-w-3xl text-sm leading-6 t-muted">{{ module.description }}</p>
                        <div class="mt-4 flex flex-wrap gap-4 text-sm t-muted"><span>Estimasi {{ module.duration_minutes }} menit</span><span v-if="assignment.deadline_at">Deadline {{ formatDateTime(assignment.deadline_at) }}</span></div>
                    </div>
                    <BaseBadge :variant="status.variant">{{ status.label }}</BaseBadge>
                </div>
            </section>

            <ol class="card grid gap-3 p-4 sm:grid-flow-col sm:auto-cols-fr" aria-label="Tahapan training">
                <li v-for="(item, index) in journeySteps" :key="item.key" class="flex items-center gap-3 rounded-xl p-3" :class="item.key === currentStep ? 'chip-brand' : 'bg-surface2'">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold" :class="completedKeys.includes(item.key) ? 'badge-ok' : 't-muted'">{{ completedKeys.includes(item.key) ? '✓' : index + 1 }}</span>
                    <span class="text-sm font-semibold t-ink">{{ item.label }}</span>
                </li>
            </ol>

            <section v-if="stage === 'cancelled'" class="card p-6 sm:p-8" role="status"><h2 class="font-display text-xl font-bold t-ink">Assignment dibatalkan</h2><p class="mt-2 text-sm t-muted">Tidak ada tindakan lanjutan untuk assignment ini.</p></section>
            <section v-else-if="stage === 'configuration_unavailable'" class="card p-6 sm:p-8" role="alert"><h2 class="font-display text-xl font-bold t-ink">Assessment sementara tidak tersedia</h2><p class="mt-2 text-sm t-muted">Konfigurasi assessment belum dapat digunakan. Hubungi admin organisasi.</p></section>
            <section v-else-if="stage === 'pretest_required'" class="card p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wide t-muted">Pretest</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Baseline assessment</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 t-muted">Pretest mengukur pemahaman Anda saat ini, diselesaikan satu kali, dan bukan penentu lulus atau gagal. Hasilnya menjadi baseline peningkatan pembelajaran.</p>
                <Link v-if="lifecycle.can_start_pretest" :href="pretestUrl" class="btn btn-primary mt-5 min-h-11">Mulai Pretest</Link>
            </section>

            <template v-else>
                <section v-if="pretestAttempt" class="card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="text-xs font-semibold uppercase tracking-wide t-muted">Baseline tercatat</p><p class="mt-1 text-sm t-muted">{{ pretestAttempt.status === 'expired' ? 'Baseline tercatat setelah waktu berakhir.' : 'Nilai ini menjadi titik awal pembelajaran.' }}</p></div>
                        <div class="text-left sm:text-right"><div class="font-display text-2xl font-bold t-ink">{{ pretestAttempt.score }}%</div><Link :href="route('user.quiz.result', pretestAttempt.id)" class="text-sm font-semibold focus-visible:outline-none focus-visible:ring-2" style="color: var(--brand)">Lihat baseline</Link></div>
                    </div>
                </section>

                <section v-if="module.content_html" class="card p-6 sm:p-8">
                    <div class="mb-6"><p class="text-xs font-semibold uppercase tracking-wide t-muted">Materi pembelajaran</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Pelajari materi</h2></div>
                    <RichContent :html="module.content_html" />
                    <div class="mt-8 border-t b-line pt-6"><button v-if="lifecycle.can_complete_content" type="button" class="btn btn-primary min-h-11 w-full justify-center" :disabled="completing" @click="markComplete">{{ completing ? 'Memproses...' : 'Tandai materi selesai' }}</button><p v-else class="text-sm t-muted">Materi pembelajaran telah ditandai selesai.</p></div>
                </section>

                <section v-if="stage === 'posttest_available'" class="card p-6 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-wide t-muted">Posttest</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Assessment akhir tersedia</h2>
                    <p class="mt-2 text-sm t-muted">Ukur pemahaman setelah pembelajaran. Anda memiliki maksimal {{ lifecycle.posttest_attempts_max }} percobaan.</p>
                    <Link v-if="lifecycle.can_start_posttest" :href="posttestUrl" class="btn btn-primary mt-5 min-h-11">Mulai Posttest</Link>
                </section>
                <section v-else-if="stage === 'posttest_cooldown'" class="card p-6 sm:p-8" role="status">
                    <p class="text-xs font-semibold uppercase tracking-wide t-muted">Masa tunggu</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Percobaan Posttest terkunci</h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="t-muted">Percobaan digunakan</dt><dd class="font-semibold t-ink">{{ lifecycle.posttest_attempts_used }} / {{ lifecycle.posttest_attempts_max }}</dd></div><div><dt class="t-muted">Skor terbaik</dt><dd class="font-semibold t-ink">{{ assignment.best_posttest_score ?? '-' }}{{ assignment.best_posttest_score !== null ? '%' : '' }}</dd></div><div><dt class="t-muted">Passing score</dt><dd class="font-semibold t-ink">{{ posttestQuiz.passing_score }}%</dd></div><div><dt class="t-muted">Tersedia kembali</dt><dd class="font-semibold t-ink">{{ formatDateTime(lifecycle.cooldown_until) }}</dd></div></dl>
                </section>
                <section v-else-if="stage === 'attempts_exhausted'" class="card p-6 sm:p-8" role="status">
                    <p class="text-xs font-semibold uppercase tracking-wide t-muted">Perlu tindak lanjut</p><h2 class="mt-2 font-display text-xl font-bold t-ink">Percobaan Posttest habis</h2>
                    <p class="mt-2 text-sm t-muted">Skor terbaik {{ assignment.best_posttest_score ?? '-' }}{{ assignment.best_posttest_score !== null ? '%' : '' }} dari passing score {{ posttestQuiz.passing_score }}%. Percobaan digunakan: {{ lifecycle.posttest_attempts_used }} / {{ lifecycle.posttest_attempts_max }}.</p>
                    <p class="mt-3 text-sm font-medium t-ink">Hubungi admin organisasi untuk tindak lanjut.</p><Link v-if="posttestAttempt" :href="route('user.quiz.result', posttestAttempt.id)" class="btn btn-secondary mt-5">Lihat hasil terakhir</Link>
                </section>
                <section v-else-if="stage === 'completed'" class="card p-6 sm:p-8">
                    <p class="text-xs font-semibold uppercase tracking-wide t-muted">Training selesai</p><h2 class="mt-2 font-display text-2xl font-bold t-ink">Selamat, perjalanan pembelajaran selesai</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><div v-if="pretestQuiz" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Baseline</div><div class="mt-1 text-2xl font-bold t-ink">{{ assignment.pretest_score }}%</div></div><div v-if="posttestAttempt" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Skor percobaan terakhir</div><div class="mt-1 text-2xl font-bold t-ink">{{ posttestAttempt.score }}%</div></div><div v-if="posttestQuiz" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Posttest terbaik</div><div class="mt-1 text-2xl font-bold t-ink">{{ assignment.best_posttest_score }}%</div></div><div v-if="posttestQuiz" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Passing score</div><div class="mt-1 text-2xl font-bold t-ink">{{ posttestQuiz.passing_score }}%</div></div><div v-if="posttestQuiz" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Percobaan digunakan</div><div class="mt-1 text-2xl font-bold t-ink">{{ lifecycle.posttest_attempts_used }} / {{ lifecycle.posttest_attempts_max }}</div></div><div v-if="learningGain !== null" class="rounded-xl bg-surface2 p-4"><div class="text-xs t-muted">Learning Gain</div><div class="mt-1 text-2xl font-bold t-ink">{{ learningGain > 0 ? '+' : '' }}{{ learningGain }} pp</div></div></div>
                    <div class="mt-6 flex flex-wrap gap-3"><Link v-if="posttestAttempt" :href="route('user.quiz.result', posttestAttempt.id)" class="btn btn-primary">Lihat hasil</Link><Link :href="route('user.training.index')" class="btn btn-secondary">Kembali ke Training</Link></div>
                    <p v-if="pretestQuiz && !posttestQuiz" class="mt-4 text-sm t-muted">Modul ini tidak menggunakan Posttest, sehingga Learning Gain tidak tersedia.</p>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
