<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    attempt: Object,
    quiz: Object,
    questions: Array,
});

const formatDate = (dateString) => {
    const d = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
};
</script>

<template>
    <Head :title="`Review: ${quiz.title}`" />

    <AppLayout :title="`Review: ${quiz.title}`">
        <Link :href="route('user.score')" class="inline-flex items-center gap-2 mb-6 transition-colors" style="color: var(--muted)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke Skor Saya
        </Link>

        <div class="card p-6 mb-6">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-sm mb-1" style="color: var(--muted)">Skor Anda</div>
                    <div class="font-display text-4xl font-bold mb-2" style="color: var(--ink)">{{ attempt.score }}</div>
                    <span 
                        class="badge"
                        :style="{ 
                            background: attempt.passed ? 'var(--brand-soft)' : 'var(--danger-bg)', 
                            color: attempt.passed ? 'var(--brand-strong)' : 'var(--danger)' 
                        }"
                    >
                        {{ attempt.passed ? 'Lulus' : 'Tidak Lulus' }}
                    </span>
                </div>
                <div class="text-right">
                    <div class="text-sm" style="color: var(--muted)">Passing Score</div>
                    <div class="font-semibold text-2xl" style="color: var(--ink)">{{ quiz.passing_score }}</div>
                    <div class="text-xs mt-1" style="color: var(--muted)">{{ formatDate(attempt.submitted_at) }}</div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div 
                v-for="(q, index) in questions" 
                :key="q.id" 
                class="card p-6"
            >
                <div class="flex items-start gap-4 mb-4">
                    <div 
                        class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm"
                        style="background: var(--surface2); color: var(--ink)"
                    >
                        {{ index + 1 }}
                    </div>
                    <div class="flex-1">
                        <div class="font-medium mb-4" style="color: var(--ink)">{{ q.question }}</div>
                        
                        <div class="space-y-2 mb-4">
                            <div 
                                v-for="(option, optIndex) in q.options" 
                                :key="optIndex"
                                class="p-3 rounded-lg border transition-colors"
                                :style="{
                                    borderColor: optIndex === q.correct_index 
                                        ? 'var(--brand)' 
                                        : (optIndex === q.user_answer_index && optIndex !== q.correct_index)
                                            ? 'var(--danger)'
                                            : 'var(--line)',
                                    background: optIndex === q.correct_index 
                                        ? 'var(--brand-soft)' 
                                        : (optIndex === q.user_answer_index && optIndex !== q.correct_index)
                                            ? 'var(--danger-bg)'
                                            : 'transparent',
                                }"
                            >
                                <div class="flex items-center gap-3">
                                    <svg 
                                        v-if="optIndex === q.correct_index"
                                        class="w-5 h-5 flex-shrink-0" 
                                        fill="none" 
                                        viewBox="0 0 24 24" 
                                        stroke="currentColor" 
                                        stroke-width="2"
                                        style="color: var(--brand)"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg 
                                        v-else-if="optIndex === q.user_answer_index && optIndex !== q.correct_index"
                                        class="w-5 h-5 flex-shrink-0" 
                                        fill="none" 
                                        viewBox="0 0 24 24" 
                                        stroke="currentColor" 
                                        stroke-width="2"
                                        style="color: var(--danger)"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span 
                                        class="flex-1"
                                        :style="{ 
                                            color: optIndex === q.correct_index 
                                                ? 'var(--brand-strong)' 
                                                : (optIndex === q.user_answer_index && optIndex !== q.correct_index)
                                                    ? 'var(--danger)'
                                                    : 'var(--ink)' 
                                        }"
                                    >
                                        {{ option }}
                                    </span>
                                    <span 
                                        v-if="optIndex === q.correct_index"
                                        class="text-xs font-medium"
                                        style="color: var(--brand-strong)"
                                    >
                                        Jawaban Benar
                                    </span>
                                    <span 
                                        v-else-if="optIndex === q.user_answer_index"
                                        class="text-xs font-medium"
                                        style="color: var(--danger)"
                                    >
                                        Jawaban Anda
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div 
                            v-if="q.explanation" 
                            class="p-4 rounded-lg"
                            style="background: var(--surface2)"
                        >
                            <div class="text-xs font-semibold mb-1" style="color: var(--muted)">PENJELASAN</div>
                            <div class="text-sm" style="color: var(--ink)">{{ q.explanation }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
