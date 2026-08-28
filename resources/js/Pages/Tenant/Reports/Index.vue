<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ stats: Object, per_user: Array });
</script>

<template>
    <Head title="Reports" />

    <AppLayout title="Laporan Training">
        <div class="flex justify-end mb-4">
            <a :href="route('tenant.reports.export')"
                class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                ⬇ Download CSV
            </a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <StatCard label="Anggota" :value="stats.users" />
            <StatCard label="Penugasan" :value="stats.assignments" />
            <StatCard label="Completion" :value="stats.completion_rate + '%'" accent="text-emerald-600" />
            <StatCard label="Pass Rate" :value="stats.pass_rate + '%'" accent="text-indigo-600" />
            <StatCard label="Rata-rata Quiz" :value="stats.avg_score" />
            <StatCard label="Awareness Org" :value="stats.org_avg" accent="text-indigo-700" border="border-2 border-indigo-200" />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100">Progres per Anggota</div>
            <table v-if="per_user.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Ditugaskan</th>
                        <th class="px-6 py-3 font-medium">Selesai</th>
                        <th class="px-6 py-3 font-medium">Percobaan</th>
                        <th class="px-6 py-3 font-medium">Rata-rata Quiz</th>
                        <th class="px-6 py-3 font-medium">Awareness Score</th>
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
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="row.awareness_score >= 70 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                {{ row.awareness_score }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada anggota." />
        </div>
    </AppLayout>
</template>