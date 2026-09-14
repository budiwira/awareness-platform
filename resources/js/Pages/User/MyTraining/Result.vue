<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    attempt: Object,
    assignment: Object,
});

const learningGain = computed(() => {
    if (!props.assignment?.pretest_score || !props.attempt?.score) return null;
    return props.attempt.score - props.assignment.pretest_score;
});
</script>

<template>
    <Head title="Hasil Quiz" />

    <AppLayout title="Hasil Quiz">
        <div class="max-w-xl mx-auto card p-8 text-center">
            <div
                class="mx-auto w-24 h-24 rounded-full flex items-center justify-center text-2xl font-bold mb-4"
                :class="attempt.passed ? 'badge-ok' : 'badge-danger'"
            >
                {{ attempt.score }}%
            </div>

            <h1 class="font-display text-2xl font-bold mb-2">{{ attempt.quiz.title }}</h1>
            <p class="text-sm mb-6" :style="{ color: attempt.passed ? 'var(--success)' : 'var(--danger)' }">
                {{ attempt.passed ? 'Lulus' : 'Belum Lulus' }}
            </p>

            <div v-if="learningGain !== null" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="text-sm font-medium mb-1" style="color: var(--t-ink);">Learning Gain</div>
                <div class="text-2xl font-bold" :style="{ color: learningGain > 0 ? 'var(--success)' : 'var(--danger)' }">
                    {{ learningGain > 0 ? '+' : '' }}{{ learningGain }}%
                </div>
                <div class="text-xs mt-1" style="color: var(--t-muted);">
                    Pretest: {{ assignment.pretest_score }}% ? Posttest: {{ attempt.score }}%
                </div>
            </div>

            <div class="flex gap-3 justify-center">
                <Link v-if="assignment" :href="route('user.training.show', assignment.id)" class="btn">
                    Kembali ke Modul
                </Link>
                <Link :href="route('user.training.index')" class="btn btn-primary">
                    Daftar Training
                </Link>
            </div>
        </div>
    </AppLayout>
</template>