<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ stats: Object, per_user: Array });
</script>

<template>
    <Head title="Reports" />

    <AppLayout title="Laporan Training">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Anggota</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ stats.users }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Penugasan</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ stats.assignments }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Completion</div>
                <div class="text-2xl font-bold text-emerald-600 mt-1">{{ stats.completion_rate }}%</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Pass Rate</div>
                <div class="text-2xl font-bold text-indigo-600 mt-1">{{ stats.pass_rate }}%</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">Rata-rata Skor</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ stats.avg_score }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Progres per Anggota</div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Ditugaskan</th>
                        <th class="px-6 py-3 font-medium">Selesai</th>
                        <th class="px-6 py-3 font-medium">Percobaan</th>
                        <th class="px-6 py-3 font-medium">Rata-rata Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in per_user" :key="row.id" class="border-b border-gray-50">
                        <td class="px-6 py-3">
                            <div class="font-medium text-gray-900">{{ row.name }}</div>
                            <div class="text-xs text-gray-500">{{ row.email }}</div>
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ row.assigned }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ row.completed }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ row.attempts }}</td>
                        <td class="px-6 py-3 font-medium" :class="row.avg_score >= 70 ? 'text-emerald-600' : 'text-gray-600'">
                            {{ row.avg_score }}
                        </td>
                    </tr>
                    <tr v-if="per_user.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada anggota.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>