<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ScoreRing from '@/Components/ScoreRing.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ score: Object, stats: Object, assignments: Array, attempts: Array });
</script>

<template>
    <Head title="My Score" />

    <AppLayout title="Skor Saya">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col items-center justify-center">
                <ScoreRing :value="score.overall" />
            </div>

            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
                <div class="font-semibold text-gray-800 mb-4">Komponen Skor (explainable)</div>
                <div class="space-y-3">
                    <div v-for="b in score.breakdown" :key="b.key">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-700">{{ b.label }} <span class="text-gray-400">({{ b.weight }}%)</span></span>
                            <span class="font-medium text-gray-900">{{ b.score }}</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="b.score >= 70 ? 'bg-emerald-500' : b.score >= 40 ? 'bg-amber-500' : 'bg-red-500'"
                                :style="{ width: b.score + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <StatCard label="Ditugaskan" :value="stats.assigned" />
            <StatCard label="Selesai" :value="stats.completed" accent="text-emerald-600" />
            <StatCard label="Percobaan Quiz" :value="stats.attempts" />
            <StatCard label="Rata-rata Quiz" :value="stats.avg_score" accent="text-indigo-600" />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Riwayat Percobaan Quiz</div>
            <table v-if="attempts.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Quiz</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                        <th class="px-6 py-3 font-medium">Hasil</th>
                        <th class="px-6 py-3 font-medium">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attempt in attempts" :key="attempt.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ attempt.quiz?.title }}</td>
                        <td class="px-6 py-3 text-gray-700">{{ attempt.score }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="attempt.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                {{ attempt.passed ? 'Lulus' : 'Gagal' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ new Date(attempt.created_at).toLocaleDateString('id-ID') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada percobaan quiz." />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Tugas Training</div>
            <table v-if="assignments.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in assignments" :key="assignment.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ assignment.module?.title }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ assignment.status === 'completed' ? 'Selesai' : 'Belum selesai' }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ assignment.score ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada tugas training." />
        </div>
    </AppLayout>
</template>