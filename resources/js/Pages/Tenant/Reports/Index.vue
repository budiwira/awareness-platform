<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ 
    summary: Object,
    trend: Object,
    risk_tiers: Object,
    users: Array,
    can_export: Boolean,
});

const filterTier = ref('semua');

const getTierLabel = (tier) => {
    const labels = {
        'baik': 'Baik',
        'cukup': 'Cukup',
        'perlu_perbaikan': 'Perlu Perbaikan',
        'belum_mengerjakan': 'Belum Mengerjakan',
    };
    return labels[tier] || tier;
};

const filteredUsers = computed(() => {
    if (filterTier.value === 'semua') return props.users;
    return props.users.filter(u => u.tier === filterTier.value);
});

const badgeStyle = (tier) => {
    if (tier === 'baik') return { background: 'var(--ok-bg)', color: 'var(--ok)' };
    if (tier === 'cukup') return { background: 'var(--warn-bg)', color: 'var(--warn)' };
    if (tier === 'perlu_perbaikan') return { background: 'var(--danger-bg)', color: 'var(--danger)' };
    return { background: 'var(--surface-2)', color: 'var(--muted)' };
};

const progressBarColor = (score) => {
    if (score >= 80) return 'var(--ok)';
    if (score >= 60) return 'var(--warn)';
    if (score > 0) return 'var(--danger)';
    return 'var(--muted)';
};

// Simple line chart using SVG
const chartWidth = 800;
const chartHeight = 200;
const padding = 40;

const getChartPath = (data, max = 100) => {
    if (!data || data.length === 0) return '';
    const width = chartWidth - padding * 2;
    const height = chartHeight - padding * 2;
    const stepX = width / (data.length - 1);
    
    return data.map((value, i) => {
        const x = padding + i * stepX;
        const y = padding + height - (value / max) * height;
        return i === 0 ? `M ${x} ${y}` : `L ${x} ${y}`;
    }).join(' ');
};

const completionPath = computed(() => getChartPath(props.trend.completion_trend, 100));
const quizScorePath = computed(() => getChartPath(props.trend.quiz_score_trend, 100));
const phishingPath = computed(() => getChartPath(props.trend.phishing_click_trend, 100));
</script>

<template>
    <Head title="Laporan" />

    <AppLayout title="Laporan & Analitik">
        <!-- Executive Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Rata-rata Awareness Score</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.avg_awareness_score }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Completion Rate</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.completion_rate }}%</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Avg Quiz Score</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.avg_quiz_score }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Phishing Click Rate</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.phishing_click_rate }}%</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Users At Risk</div>
                <div class="font-display text-4xl font-bold" style="color: var(--danger)">{{ summary.users_at_risk }}</div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="card p-6 mb-8">
            <div class="font-semibold mb-4 t-ink">Tren 30 Hari Terakhir</div>
            <svg :width="chartWidth" :height="chartHeight" class="w-full" style="max-width: 100%; height: auto;">
                <!-- Grid lines -->
                <line v-for="i in 5" :key="'grid-' + i" 
                    :x1="padding" :y1="padding + (chartHeight - padding * 2) * i / 5" 
                    :x2="chartWidth - padding" :y2="padding + (chartHeight - padding * 2) * i / 5" 
                    stroke="var(--line)" stroke-width="1" />
                
                <!-- Completion trend -->
                <path :d="completionPath" stroke="var(--brand)" stroke-width="2" fill="none" />
                
                <!-- Quiz score trend -->
                <path :d="quizScorePath" stroke="var(--ok)" stroke-width="2" fill="none" />
                
                <!-- Phishing click trend -->
                <path :d="phishingPath" stroke="var(--danger)" stroke-width="2" fill="none" />
                
                <!-- Y-axis labels -->
                <text x="10" :y="padding" class="text-xs" fill="var(--muted)">100%</text>
                <text x="10" :y="padding + (chartHeight - padding * 2) / 2" class="text-xs" fill="var(--muted)">50%</text>
                <text x="10" :y="chartHeight - padding" class="text-xs" fill="var(--muted)">0%</text>
            </svg>
            
            <div class="flex items-center gap-6 mt-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-1 rounded" style="background: var(--brand)"></div>
                    <span class="t-muted">Completion</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-1 rounded" style="background: var(--ok)"></div>
                    <span class="t-muted">Quiz Score</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-1 rounded" style="background: var(--danger)"></div>
                    <span class="t-muted">Phishing Click</span>
                </div>
            </div>
        </div>

        <!-- Risk Tier Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Baik</div>
                <div class="font-display text-3xl font-bold" style="color: var(--ok)">{{ risk_tiers.baik }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Cukup</div>
                <div class="font-display text-3xl font-bold" style="color: var(--warn)">{{ risk_tiers.cukup }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Perlu Perbaikan</div>
                <div class="font-display text-3xl font-bold" style="color: var(--danger)">{{ risk_tiers.perlu_perbaikan }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Belum Mengerjakan</div>
                <div class="font-display text-3xl font-bold t-muted">{{ risk_tiers.belum_mengerjakan }}</div>
            </div>
        </div>

        <!-- User Risk Table -->
        <div class="card overflow-hidden">
            <div class="px-6 py-4 flex justify-between items-center border-b" style="border-color: var(--line)">
                <div>
                    <div class="font-semibold t-ink">Detail per User</div>
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
                <a 
                    v-if="can_export" 
                    :href="route('tenant.reports.export')" 
                    class="btn btn-secondary"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </a>
                <div v-else class="text-sm t-muted">
                    Export tidak tersedia di Package Anda
                </div>
            </div>
            <table v-if="filteredUsers.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium text-right">Awareness Score</th>
                        <th class="px-6 py-3 font-medium text-right">Completion</th>
                        <th class="px-6 py-3 font-medium text-right">Phishing Clicked</th>
                        <th class="px-6 py-3 font-medium">Tier</th>
                        <th class="px-6 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in filteredUsers" :key="user.id" class="border-b transition-colors hover:bg-surface-2" style="border-color: var(--line)">
                        <td class="px-6 py-3">
                            <div class="font-medium t-ink">{{ user.name }}</div>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ user.email }}</td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="h-1.5 rounded-full overflow-hidden" style="width: 60px; background: var(--surface-2);">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :style="{ width: user.awareness_score + '%', backgroundColor: progressBarColor(user.awareness_score) }"
                                    ></div>
                                </div>
                                <span class="font-semibold t-ink">{{ user.awareness_score }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ user.completion_rate }}%</td>
                        <td class="px-6 py-3 text-right font-semibold t-ink">{{ user.phishing_clicked }}</td>
                        <td class="px-6 py-3">
                            <span class="badge" :style="badgeStyle(user.tier)">
                                {{ getTierLabel(user.tier) }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <Link 
                                :href="route('tenant.reports.users.show', user.id)" 
                                class="text-sm font-medium transition-colors"
                                style="color: var(--brand)"
                                @mouseenter="$event.target.style.color = 'var(--brand-mid)'"
                                @mouseleave="$event.target.style.color = 'var(--brand)'"
                            >
                                Lihat Detail
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Tidak ada user yang cocok dengan filter." />
        </div>
    </AppLayout>
</template>
