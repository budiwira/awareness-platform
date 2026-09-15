<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    attempt: Object,
    assignment: Object,
});

const learningGain = computed(() => {
    if (props.assignment?.pretest_score === null || props.assignment?.pretest_score === undefined || props.attempt?.score === null || props.attempt?.score === undefined) return null;
    return props.attempt.score - props.assignment.pretest_score;
});
</script>

<template>
    <Head title="Hasil Quiz" />

    <AppLayout title="Hasil Quiz">
        <div class="max-w-2xl mx-auto space-y-6">
            <div class="card p-8 text-center">
                <div class="text-sm font-medium mb-2 t-muted">HASIL QUIZ</div>
                <h1 class="font-display text-3xl font-bold mb-6 t-ink">{{ attempt.quiz.title }}</h1>
                <div class="mx-auto w-28 h-28 rounded-2xl flex items-center justify-center text-3xl font-display font-bold mb-4"
                    :class="attempt.passed ? 'badge-ok' : 'badge-warn'">
                    {{ attempt.score }}%
                </div>
                <span class="badge" :class="attempt.passed ? 'badge-ok' : 'badge-warn'">
                    {{ attempt.passed ? 'Lulus' : 'Belum lulus' }}
                </span>
                <p class="mt-4 t-muted">
                    {{ attempt.passed ? 'Nilai Anda sudah memenuhi standar kelulusan.' : 'Pelajari kembali materi dan coba lagi untuk mencapai nilai kelulusan.' }}
                </p>
            </div>

            <div v-if="learningGain !== null" class="card p-6">
                <div class="text-sm font-medium mb-1 t-muted">Learning gain</div>
                <div class="text-2xl font-bold" :style="{ color: learningGain >= 0 ? 'var(--success)' : 'var(--danger)' }">
                    {{ learningGain > 0 ? '+' : '' }}{{ learningGain }}%
                </div>
                <div class="text-sm mt-1 t-muted">
                    Perbandingan nilai pretest {{ assignment.pretest_score }}% dan hasil quiz {{ attempt.score }}%.
                </div>
            </div>

            <div class="card p-6 flex flex-wrap gap-3 justify-center">
                <Link :href="route('user.training.quiz.review', attempt.id)" class="btn">
                    Review jawaban
                </Link>
                <Link v-if="assignment" :href="route('user.training.show', assignment.id)" class="btn btn-primary">
                    Kembali ke training
                </Link>
                <Link v-else :href="route('user.training.index')" class="btn btn-primary">
                    Daftar training
                </Link>
            </div>
        </div>
    </AppLayout>
</template>