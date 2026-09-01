<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    attempt: Object,
    assignment_id: { type: Number, default: null },
});
</script>

<template>
    <Head title="Hasil Quiz" />

    <AppLayout title="Hasil Quiz">
        <div class="max-w-xl mx-auto card p-8 text-center">
            <div
                class="mx-auto w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold mb-4"
                :class="attempt.passed ? 'badge-ok' : 'bg-red-100 text-red-700'"
            >
                {{ attempt.score }}
            </div>

            <h2 class="text-xl font-bold t-ink mb-1">{{ attempt.quiz.title }}</h2>
            <p class="text-sm t-muted mb-6">Nilai kelulusan: {{ attempt.quiz.passing_score }}%</p>

            <span
                class="inline-block px-4 py-1.5 rounded-full text-sm font-medium mb-8"
                :class="attempt.passed ? 'badge-ok' : 'bg-red-100 text-red-700'"
            >
                {{ attempt.passed ? '✓ LULUS — Modul selesai' : 'BELUM LULUS — Coba lagi' }}
            </span>

            <div class="flex justify-center gap-3">
                <Link
                    :href="route('user.training.index')"
                    class="btn btn-secondary"
                >
                    Ke Daftar Training
                </Link>
                <Link
                    v-if="!attempt.passed && assignment_id"
                    :href="route('user.training.quiz', assignment_id)"
                    class="btn btn-primary"
                >
                    Ulangi Quiz
                </Link>
            </div>
        </div>
    </AppLayout>
</template>