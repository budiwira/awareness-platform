<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ 
    report: Object,
});

const getTierLabel = (tier) => {
    const labels = {
        'baik': 'Baik',
        'cukup': 'Cukup',
        'perlu_perbaikan': 'Perlu Perbaikan',
        'belum_mengerjakan': 'Belum Mengerjakan',
    };
    return labels[tier] || tier;
};

const getTierColor = (tier) => {
    if (tier === 'baik') return 'var(--ok)';
    if (tier === 'cukup') return 'var(--warn)';
    if (tier === 'perlu_perbaikan') return 'var(--danger)';
    return 'var(--muted)';
};

const getAssignmentStatusLabel = (status) => {
    const labels = {
        'completed': 'Selesai',
        'in_progress': 'Sedang Berjalan',
        'assigned': 'Ditugaskan',
    };
    return labels[status] || status;
};
</script>

<template>
    <Head :title="`Detail Laporan: ${report.user.name}`" />

    <AppLayout :title="`Detail Laporan: ${report.user.name}`">
        <Link :href="route('tenant.reports')" class="inline-flex items-center gap-2 text-sm mb-6 t-muted transition-colors hover:t-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke semua laporan
        </Link>

        <!-- User Profile Summary -->
        <div class="card p-6 mb-8 bg-app">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5">
                <div>
                    <div class="text-sm font-medium t-muted mb-2">Ringkasan profil pengguna</div>
                    <h2 class="font-display text-2xl font-bold mb-1 t-ink">{{ report.user.name }}</h2>
                    <div class="text-sm t-muted mb-2">{{ report.user.email }}</div>
                    <div class="text-xs t-muted">Peran: {{ report.user.role }}</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-xs t-muted mb-1">Tingkat risiko</div>
                    <div class="font-display text-2xl font-bold" :style="{ color: getTierColor(report.summary.tier) }">
                        {{ getTierLabel(report.summary.tier) }}
                    </div>
                    <div class="text-xs t-muted mt-1">Berdasarkan aktivitas pelatihan dan simulasi</div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Skor Kesadaran</div>
                <div class="font-display text-4xl font-bold t-ink">{{ report.summary.avg_awareness_score }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Tingkat Penyelesaian</div>
                <div class="font-display text-4xl font-bold t-ink">{{ report.summary.completion_rate }}%</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Pelatihan Selesai</div>
                <div class="font-display text-4xl font-bold t-ink">
                    {{ report.summary.completed_assignments }}<span class="text-xl t-muted">/{{ report.summary.total_assignments }}</span>
                </div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Phishing Diklik</div>
                <div class="font-display text-4xl font-bold" style="color: var(--danger)">
                    {{ report.summary.phishing_clicked }}<span class="text-xl t-muted">/{{ report.summary.phishing_received }}</span>
                </div>
            </div>
        </div>

        <!-- Penugasan Pelatihan -->
        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 border-b b-line">
                <div class="font-semibold t-ink">Penugasan Pelatihan</div>
                <p class="text-sm t-muted mt-1">Status modul yang ditugaskan kepada pengguna ini.</p>
            </div>
            <table v-if="report.assignments.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b b-line t-muted">
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Nilai</th>
                        <th class="px-6 py-3 font-medium">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in report.assignments" :key="assignment.id" class="border-b b-line">
                        <td class="px-6 py-3 font-medium t-ink">{{ assignment.module_title }}</td>
                        <td class="px-6 py-3">
                            <span v-if="assignment.status === 'completed'" class="badge badge-ok">Selesai</span>
                            <span v-else class="badge badge-warn">{{ getAssignmentStatusLabel(assignment.status) }}</span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ assignment.score ?? '-' }}</td>
                        <td class="px-6 py-3 t-muted">{{ assignment.completed_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                <div class="font-medium t-ink">Belum ada penugasan pelatihan</div>
                <div class="text-sm mt-1">Penugasan baru akan muncul di sini setelah diberikan kepada pengguna.</div>
            </div>
        </div>

        <!-- Percobaan Kuis -->
        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 border-b b-line">
                <div class="font-semibold t-ink">Percobaan Kuis</div>
                <p class="text-sm t-muted mt-1">Riwayat nilai dan status kelulusan setiap percobaan.</p>
            </div>
            <table v-if="report.quiz_attempts.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b b-line t-muted">
                        <th class="px-6 py-3 font-medium">Quiz</th>
                        <th class="px-6 py-3 font-medium text-right">Nilai</th>
                        <th class="px-6 py-3 font-medium">Status Kelulusan</th>
                        <th class="px-6 py-3 font-medium">Tanggal Pengumpulan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attempt in report.quiz_attempts" :key="attempt.id" class="border-b b-line">
                        <td class="px-6 py-3 font-medium t-ink">{{ attempt.quiz_title }}</td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ attempt.score }}</td>
                        <td class="px-6 py-3">
                            <span v-if="attempt.passed" class="badge badge-ok">Lulus</span>
                            <span v-else class="badge badge-warn">Belum Lulus</span>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ attempt.submitted_at }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                <div class="font-medium t-ink">Belum ada percobaan kuis</div>
                <div class="text-sm mt-1">Hasil kuis akan muncul setelah pengguna mengumpulkan jawaban.</div>
            </div>
        </div>

        <!-- Riwayat Simulasi Phishing -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b b-line">
                <div class="font-semibold t-ink">Riwayat Simulasi Phishing</div>
                <p class="text-sm t-muted mt-1">Respons pengguna terhadap setiap simulasi yang diterima.</p>
            </div>
            <table v-if="report.phishing_history.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b b-line t-muted">
                        <th class="px-6 py-3 font-medium">Kampanye</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Waktu Klik</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="target in report.phishing_history" :key="target.id" class="border-b b-line">
                        <td class="px-6 py-3 font-medium t-ink">{{ target.campaign_title }}</td>
                        <td class="px-6 py-3">
                            <span v-if="target.clicked_at" class="badge badge-warn">Diklik</span>
                            <span v-else class="badge badge-ok">Tidak Diklik</span>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ target.clicked_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                <div class="font-medium t-ink">Belum ada riwayat simulasi phishing</div>
                <div class="text-sm mt-1">Riwayat akan muncul setelah pengguna menerima simulasi.</div>
            </div>
        </div>
    </AppLayout>
</template>
