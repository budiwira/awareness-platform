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
</script>

<template>
    <Head :title="`Detail Report: ${report.user.name}`" />

    <AppLayout :title="`Detail Report: ${report.user.name}`">
        <Link :href="route('tenant.reports')" class="inline-flex items-center gap-2 text-sm mb-6 t-muted transition-colors hover:t-ink">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Laporan
        </Link>

        <!-- User Profile Summary -->
        <div class="card p-6 mb-8">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="font-display text-2xl font-bold mb-1 t-ink">{{ report.user.name }}</h2>
                    <div class="text-sm t-muted mb-2">{{ report.user.email }}</div>
                    <div class="text-xs t-muted">Role: {{ report.user.role }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs t-muted mb-1">Risk Tier</div>
                    <div class="font-display text-2xl font-bold" :style="{ color: getTierColor(report.summary.tier) }">
                        {{ getTierLabel(report.summary.tier) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Awareness Score</div>
                <div class="font-display text-4xl font-bold t-ink">{{ report.summary.avg_awareness_score }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Completion Rate</div>
                <div class="font-display text-4xl font-bold t-ink">{{ report.summary.completion_rate }}%</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Completed</div>
                <div class="font-display text-4xl font-bold t-ink">
                    {{ report.summary.completed_assignments }}<span class="text-xl t-muted">/{{ report.summary.total_assignments }}</span>
                </div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Phishing Clicked</div>
                <div class="font-display text-4xl font-bold" style="color: var(--danger)">
                    {{ report.summary.phishing_clicked }}<span class="text-xl t-muted">/{{ report.summary.phishing_received }}</span>
                </div>
            </div>
        </div>

        <!-- Training Assignments -->
        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 border-b" style="border-color: var(--line)">
                <div class="font-semibold t-ink">Training Assignments</div>
            </div>
            <table v-if="report.assignments.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Score</th>
                        <th class="px-6 py-3 font-medium">Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in report.assignments" :key="assignment.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3 font-medium t-ink">{{ assignment.module_title }}</td>
                        <td class="px-6 py-3">
                            <span v-if="assignment.status === 'completed'" class="badge" style="background: var(--ok-bg); color: var(--ok)">Completed</span>
                            <span v-else class="badge" style="background: var(--surface-2); color: var(--muted)">{{ assignment.status }}</span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ assignment.score ?? '-' }}</td>
                        <td class="px-6 py-3 t-muted">{{ assignment.completed_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                Belum ada assignment
            </div>
        </div>

        <!-- Quiz Attempts -->
        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 border-b" style="border-color: var(--line)">
                <div class="font-semibold t-ink">Quiz Attempts</div>
            </div>
            <table v-if="report.quiz_attempts.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Quiz</th>
                        <th class="px-6 py-3 font-medium text-right">Score</th>
                        <th class="px-6 py-3 font-medium">Passed</th>
                        <th class="px-6 py-3 font-medium">Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="attempt in report.quiz_attempts" :key="attempt.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3 font-medium t-ink">{{ attempt.quiz_title }}</td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ attempt.score }}</td>
                        <td class="px-6 py-3">
                            <span v-if="attempt.passed" class="badge" style="background: var(--ok-bg); color: var(--ok)">Lulus</span>
                            <span v-else class="badge" style="background: var(--danger-bg); color: var(--danger)">Tidak Lulus</span>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ attempt.submitted_at }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                Belum ada quiz attempts
            </div>
        </div>

        <!-- Phishing History -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 border-b" style="border-color: var(--line)">
                <div class="font-semibold t-ink">Phishing Simulation History</div>
            </div>
            <table v-if="report.phishing_history.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Campaign</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Clicked At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="target in report.phishing_history" :key="target.id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3 font-medium t-ink">{{ target.campaign_title }}</td>
                        <td class="px-6 py-3">
                            <span v-if="target.clicked_at" class="badge" style="background: var(--danger-bg); color: var(--danger)">Clicked</span>
                            <span v-else class="badge" style="background: var(--ok-bg); color: var(--ok)">Not Clicked</span>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ target.clicked_at ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                Belum ada phishing simulation
            </div>
        </div>
    </AppLayout>
</template>
