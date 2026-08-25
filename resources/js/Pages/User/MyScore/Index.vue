<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ stats: Object, assignments: Array, attempts: Array });
</script>

<template>
    <Head title="My Score" />

    <AppLayout title="Skor Saya">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Ditugaskan</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ stats.assigned }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Selesai</div>
                <div class="text-2xl font-bold text-emerald-600 mt-1">{{ stats.completed }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Percobaan Quiz</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ stats.attempts }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Rata-rata Skor</div>
                <div class="text-2xl font-bold text-indigo-600 mt-1">{{ stats.avg_score }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Riwayat Percobaan Quiz</div>
            <table class="w-full text-sm">
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
                    <tr v-if="attempts.length === 0">
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Belum ada percobaan quiz.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Tugas Training</div>
            <table class="w-full text-sm">
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
                    <tr v-if="assignments.length === 0">
                        <td colspan="3" class="px-6 py-8 text-center text-gray-500">Belum ada tugas training.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>