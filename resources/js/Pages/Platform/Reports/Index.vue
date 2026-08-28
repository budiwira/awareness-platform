<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ rows: Array, platform_avg: Number });
</script>

<template>
    <Head title="Platform Reports" />

    <AppLayout title="Laporan Lintas Tenant">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">Kesehatan awareness seluruh organisasi di platform.</p>
            <div class="bg-white rounded-xl shadow-sm px-5 py-3 border-2 border-indigo-200">
                <span class="text-xs text-indigo-600 uppercase tracking-wide">Awareness Platform</span>
                <span class="ml-3 text-2xl font-bold text-indigo-700">{{ platform_avg }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Organisasi</th>
                        <th class="px-6 py-3 font-medium">Anggota</th>
                        <th class="px-6 py-3 font-medium">Penugasan</th>
                        <th class="px-6 py-3 font-medium">Completion</th>
                        <th class="px-6 py-3 font-medium">Rata-rata Awareness</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ row.name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ row.users }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ row.assignments }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ row.completion_rate }}%</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="row.avg_awareness >= 70 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                {{ row.avg_awareness }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada tenant.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>