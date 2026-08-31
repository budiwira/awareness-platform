<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ stats: Object, per_user: Array });

const avgMemberScore = computed(() => {
    if (props.per_user.length === 0) return 0;
    const sum = props.per_user.reduce((acc, u) => acc + (u.awareness_score || 0), 0);
    return Math.round(sum / props.per_user.length);
});

const totalMembers = computed(() => props.per_user.length);

const completedAssignments = computed(() => {
    return props.per_user.reduce((acc, u) => acc + (u.completed || 0), 0);
});

const progressBarColor = (score) => {
    if (score >= 70) return '#0f766e';
    if (score >= 40) return '#f59e0b';
    return '#e11d48';
};

const badgeStyle = (score) => {
    if (score >= 70) return { background: '#ccfbf1', color: '#115e59' };
    if (score >= 40) return { background: '#fef3c7', color: '#92400e' };
    return { background: '#fee2e2', color: '#991b1b' };
};
</script>

<template>
    <Head title="Reports" />

    <AppLayout title="Laporan Training">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2" style="color: var(--muted)">Rata-rata Skor Anggota</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ink)">{{ avgMemberScore }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2" style="color: var(--muted)">Jumlah Anggota</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ink)">{{ totalMembers }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2" style="color: var(--muted)">Penugasan Selesai</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ink)">{{ completedAssignments }}</div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-6 py-4 flex justify-between items-center border-b" style="border-color: var(--line)">
                <div class="font-semibold" style="color: var(--ink)">Progres per Anggota</div>
                <a :href="route('tenant.reports.export')" class="btn btn-secondary">
                    Download CSV
                </a>
            </div>
            <table v-if="per_user.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b" style="color: var(--muted); border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Anggota</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Rata-rata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in per_user" :key="row.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3">
                            <div class="font-medium" style="color: var(--ink)">{{ row.name }}</div>
                            <div class="text-xs" style="color: var(--muted)">{{ row.email }}</div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden" style="width: 80px;">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :style="{ width: row.awareness_score + '%', backgroundColor: progressBarColor(row.awareness_score) }"
                                    ></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="badge" :style="badgeStyle(row.awareness_score)">
                                {{ row.awareness_score >= 70 ? 'Baik' : row.awareness_score >= 40 ? 'Cukup' : 'Perlu Perbaikan' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold" style="color: var(--ink)">{{ row.awareness_score }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada anggota." />
        </div>
    </AppLayout>
</template>