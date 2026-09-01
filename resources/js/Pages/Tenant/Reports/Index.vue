<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ stats: Object, per_user: Array });

const filterTier = ref('semua'); // 'semua' | 'baik' | 'cukup' | 'perlu_perbaikan' | 'belum_mengerjakan'

const getTier = (score) => {
    if (score >= 70) return 'baik';
    if (score >= 40) return 'cukup';
    if (score > 0) return 'perlu_perbaikan';
    return 'belum_mengerjakan';
};

const getTierLabel = (score) => {
    if (score >= 70) return 'Baik';
    if (score >= 40) return 'Cukup';
    if (score > 0) return 'Perlu Perbaikan';
    return 'Belum Mengerjakan';
};

const filteredUsers = computed(() => {
    if (filterTier.value === 'semua') return props.per_user;
    return props.per_user.filter(u => getTier(u.awareness_score) === filterTier.value);
});

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
    if (score > 0) return '#e11d48';
    return '#9ca3af';
};

const badgeStyle = (score) => {
    if (score >= 70) return { background: '#ccfbf1', color: '#115e59' };
    if (score >= 40) return { background: '#fef3c7', color: '#92400e' };
    if (score > 0) return { background: '#fee2e2', color: '#991b1b' };
    return { background: '#f3f4f6', color: '#6b7280' };
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
                <div>
                    <div class="font-semibold" style="color: var(--ink)">Progres per Anggota</div>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                        <button 
                            @click="filterTier = 'semua'" 
                            class="chip"
                            :class="filterTier === 'semua' ? 'chip-active' : ''"
                        >
                            Semua
                        </button>
                        <button 
                            @click="filterTier = 'baik'" 
                            class="chip"
                            :class="filterTier === 'baik' ? 'chip-active' : ''"
                        >
                            Baik
                        </button>
                        <button 
                            @click="filterTier = 'cukup'" 
                            class="chip"
                            :class="filterTier === 'cukup' ? 'chip-active' : ''"
                        >
                            Cukup
                        </button>
                        <button 
                            @click="filterTier = 'perlu_perbaikan'" 
                            class="chip"
                            :class="filterTier === 'perlu_perbaikan' ? 'chip-active' : ''"
                        >
                            Perlu Perbaikan
                        </button>
                        <button 
                            @click="filterTier = 'belum_mengerjakan'" 
                            class="chip"
                            :class="filterTier === 'belum_mengerjakan' ? 'chip-active' : ''"
                        >
                            Belum Mengerjakan
                        </button>
                    </div>
                </div>
                <a :href="route('tenant.reports.export')" class="btn btn-secondary">
                    Download CSV
                </a>
            </div>
            <table v-if="filteredUsers.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b" style="color: var(--muted); border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Anggota</th>
                        <th class="px-6 py-3 font-medium">Skor</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Rata-rata</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filteredUsers" :key="row.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3">
                            <div class="font-medium" style="color: var(--ink)">{{ row.name }}</div>
                            <div class="text-xs" style="color: var(--muted)">{{ row.email }}</div>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 bg-surface2 rounded-full overflow-hidden" style="width: 80px;">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :style="{ width: row.awareness_score + '%', backgroundColor: progressBarColor(row.awareness_score) }"
                                    ></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span class="badge" :style="badgeStyle(row.awareness_score)">
                                {{ getTierLabel(row.awareness_score) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold" style="color: var(--ink)">{{ row.awareness_score }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Tidak ada anggota yang cocok dengan filter." />
        </div>
    </AppLayout>
</template>