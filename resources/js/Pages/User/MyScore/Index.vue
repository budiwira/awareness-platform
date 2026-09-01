<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ScoreRing from '@/Components/ScoreRing.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ score: Object, stats: Object, assignments: Array, attempts: Array });

const passedQuizCount = computed(() => props.attempts.filter(a => a.passed).length);
const participationCount = computed(() => props.stats.completed);

const progressBarColor = (score) => {
    if (score >= 70) return 'var(--brand)';
    if (score >= 40) return 'var(--warn)';
    return 'var(--danger)';
};
</script>

<template>
    <Head title="My Score" />

    <AppLayout title="Skor Saya">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="card p-8 flex flex-col items-center justify-center">
                <ScoreRing :value="score.overall" />
                <div class="mt-2 font-display text-lg font-semibold" style="color: var(--ink)">Awareness Score</div>
            </div>

            <div class="card p-6 flex flex-col justify-center">
                <p class="text-sm mb-4" style="color: var(--muted); line-height: 1.6;">
                    Awareness Score adalah ukuran kompetensi keamanan siber Anda yang dihitung dari berbagai aktivitas training: penyelesaian modul, skor quiz, partisipasi case study, dan performa CTF.
                </p>
                <div class="flex gap-2">
                    <span class="badge" style="background: var(--brand-soft); color: var(--brand-strong);">
                        {{ passedQuizCount }} quiz lulus
                    </span>
                    <span class="badge" style="background: var(--brand-soft); color: var(--brand-strong);">
                        {{ participationCount }} modul selesai
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div v-for="b in score.breakdown" :key="b.key" class="card p-4">
                <div class="text-xs mb-1" style="color: var(--muted)">{{ b.label }}</div>
                <div class="font-display text-3xl font-bold mb-2" style="color: var(--ink)">{{ b.score }}</div>
                <div class="h-1.5 bg-surface2 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all"
                        :style="{ width: b.score + '%', backgroundColor: progressBarColor(b.score) }"
                    ></div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 font-semibold border-b" style="color: var(--ink); border-color: var(--line)">Riwayat Percobaan Quiz</div>
            <table v-if="attempts.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b" style="color: var(--muted); border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Quiz</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                        <th class="px-6 py-3 font-medium">Hasil</th>
                        <th class="px-6 py-3 font-medium">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attempt in attempts" :key="attempt.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3 font-medium" style="color: var(--ink)">{{ attempt.quiz?.title }}</td>
                        <td class="px-6 py-3" style="color: var(--ink)">{{ attempt.score }}</td>
                        <td class="px-6 py-3">
                            <span class="badge" :style="{ background: attempt.passed ? 'var(--brand-soft)' : 'var(--danger-bg)', color: attempt.passed ? 'var(--brand-strong)' : 'var(--danger)' }">
                                {{ attempt.passed ? 'Lulus' : 'Gagal' }}
                            </span>
                        </td>
                        <td class="px-6 py-3" style="color: var(--muted)">{{ new Date(attempt.created_at).toLocaleDateString('id-ID') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada percobaan quiz." />
        </div>

        <div class="card overflow-hidden">
            <div class="px-6 py-4 font-semibold border-b" style="color: var(--ink); border-color: var(--line)">Tugas Training</div>
            <table v-if="assignments.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b" style="color: var(--muted); border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in assignments" :key="assignment.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3 font-medium" style="color: var(--ink)">{{ assignment.module?.title }}</td>
                        <td class="px-6 py-3" style="color: var(--muted)">{{ assignment.status === 'completed' ? 'Selesai' : 'Belum selesai' }}</td>
                        <td class="px-6 py-3" style="color: var(--muted)">{{ assignment.score ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada tugas training." />
        </div>
    </AppLayout>
</template>