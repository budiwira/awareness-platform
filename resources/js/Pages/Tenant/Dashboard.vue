<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseCard } from '@/Components';
import ScoreRing from '@/Components/ScoreRing.vue';

const props = defineProps({
    stats: Object,
    phishingStats: Object,
});

const tierPercent = (count) => {
    const total = props.stats.tier_baik + props.stats.tier_cukup + props.stats.tier_perlu_perbaikan + props.stats.tier_belum_mengerjakan;
    return total > 0 ? (count / total * 100).toFixed(1) : 0;
};

const tenantName = computed(() => usePage().props.auth?.user?.tenant_name ?? 'Organisasi');
</script>

<template>
    <Head title="Tenant Dashboard" />

    <AppLayout title="Dashboard Organisasi">
        <!-- Hero section -->
        <div
            class="rounded-2xl p-8 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6 relative"
            style="background: linear-gradient(140deg, var(--brand) 0%, var(--brand-mid) 55%, var(--brand-strong) 100%)"
        >
            <div>
                <div class="text-sm mb-1 t-on-hero-muted">Organisasi</div>
                <h2 class="font-display text-2xl font-bold t-on-hero">{{ tenantName }}</h2>
                <p class="text-sm mt-2 max-w-xl t-on-hero-muted">
                    Pantau kesiapan keamanan anggota: tugaskan training, jalankan simulasi TTX,
                    dan ukur hasilnya lewat laporan awareness.
                </p>
            </div>
            <div class="flex gap-4">
                <div class="rounded-xl px-5 py-3 text-center" style="background: rgba(237,233,254,.1)">
                    <div class="text-2xl font-bold t-on-hero">{{ stats.active_users }}<span class="text-sm t-on-hero-muted">/{{ stats.total_users }}</span></div>
                    <div class="text-[11px] t-on-hero-muted">Anggota Aktif</div>
                </div>
                <div class="rounded-xl px-5 py-3 text-center" style="background: rgba(237,233,254,.1)">
                    <div class="text-2xl font-bold t-on-hero">{{ stats.admins }}</div>
                    <div class="text-[11px] t-on-hero-muted">Admin</div>
                </div>
            </div>
        </div>

        <!-- Stats utama dengan ScoreRing -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <BaseCard>
                <div class="flex flex-col items-center">
                    <ScoreRing :value="stats.avg_awareness_score" :size="120" label="Rata-rata Skor Kesadaran" />
                </div>
            </BaseCard>

            <BaseCard>
                <div class="flex flex-col items-center">
                    <ScoreRing :value="stats.completion_rate" :size="120" label="Tingkat Penyelesaian" />
                </div>
            </BaseCard>

            <BaseCard title="Distribusi Kesadaran">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="chip-brand">Baik</span>
                        <span class="font-semibold" style="color: var(--ink)">{{ stats.tier_baik }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="badge-warn">Cukup</span>
                        <span class="font-semibold" style="color: var(--ink)">{{ stats.tier_cukup }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="badge-danger">Perlu Perbaikan</span>
                        <span class="font-semibold" style="color: var(--ink)">{{ stats.tier_perlu_perbaikan }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="t-muted">Belum Mengerjakan</span>
                        <span class="font-semibold" style="color: var(--ink)">{{ stats.tier_belum_mengerjakan }}</span>
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Distribusi visual bar -->
        <BaseCard title="Distribusi Visual" class="mb-8">
            <div class="flex gap-1 h-8 rounded-lg overflow-hidden">
                <div
                    v-if="stats.tier_baik > 0"
                    class="transition-all"
                    style="background: var(--ok)"
                    :style="{ width: tierPercent(stats.tier_baik) + '%' }"
                    :title="`Baik: ${stats.tier_baik}`"
                ></div>
                <div
                    v-if="stats.tier_cukup > 0"
                    class="transition-all"
                    style="background: var(--warn)"
                    :style="{ width: tierPercent(stats.tier_cukup) + '%' }"
                    :title="`Cukup: ${stats.tier_cukup}`"
                ></div>
                <div
                    v-if="stats.tier_perlu_perbaikan > 0"
                    class="transition-all"
                    style="background: var(--danger)"
                    :style="{ width: tierPercent(stats.tier_perlu_perbaikan) + '%' }"
                    :title="`Perlu Perbaikan: ${stats.tier_perlu_perbaikan}`"
                ></div>
                <div
                    v-if="stats.tier_belum_mengerjakan > 0"
                    class="transition-all"
                    style="background: var(--line)"
                    :style="{ width: tierPercent(stats.tier_belum_mengerjakan) + '%' }"
                    :title="`Belum Mengerjakan: ${stats.tier_belum_mengerjakan}`"
                ></div>
            </div>
            <div class="flex items-center gap-6 mt-4 text-xs flex-wrap">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background: var(--ok)"></div>
                    <span class="text-xs t-muted">Baik ({{ stats.tier_baik }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background: var(--warn)"></div>
                    <span class="text-xs t-muted">Cukup ({{ stats.tier_cukup }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background: var(--danger)"></div>
                    <span class="text-xs t-muted">Perlu Perbaikan ({{ stats.tier_perlu_perbaikan }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded" style="background: var(--line)"></div>
                    <span style="color: var(--muted)">Belum Mengerjakan ({{ stats.tier_belum_mengerjakan }})</span>
                </div>
            </div>
        </BaseCard>

        <!-- Phishing stats -->
        <BaseCard v-if="phishingStats" class="mb-8">
            <template #header>
                <div class="flex items-center justify-between w-full">
                    <h3 class="font-display font-semibold t-ink">Phishing Awareness</h3>
                    <Link :href="route('tenant.phishing.index')" class="text-sm text-brand hover:underline">
                        Kelola Kampanye ?
                    </Link>
                </div>
            </template>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-surface-alt">
                    <div class="text-xs t-muted mb-1">Rata-rata Click Rate</div>
                    <div class="font-display text-2xl font-bold t-ink">{{ phishingStats.avg_click_rate }}%</div>
                    <div class="text-xs t-muted mt-1">Lebih rendah lebih baik</div>
                </div>
                <div class="p-4 rounded-xl bg-surface-alt">
                    <div class="text-xs t-muted mb-1">Kampanye Terkirim</div>
                    <div class="font-display text-2xl font-bold t-ink">{{ phishingStats.campaigns_sent }}</div>
                    <div class="text-xs t-muted mt-1">Total simulasi</div>
                </div>
                <div class="p-4 rounded-xl bg-surface-alt">
                    <div class="text-xs t-muted mb-1">Pengguna Berisiko</div>
                    <div class="font-display text-2xl font-bold text-danger">{{ phishingStats.users_at_risk }}</div>
                    <div class="text-xs t-muted mt-1">Click rate > 50%</div>
                </div>
            </div>
        </BaseCard>

        <!-- Quick actions -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link :href="route('tenant.assignments.index')">
                <BaseCard interactive>
                    <div class="flex items-center justify-between">
                        <div class="font-display font-semibold t-ink">Penugasan Training</div>
                        <span class="chip-brand">?</span>
                    </div>
                    <p class="text-sm mt-1" style="color: var(--muted)">Tugaskan modul ke anggota.</p>
                </BaseCard>
            </Link>
            <Link :href="route('tenant.ttx.exercises.index')">
                <BaseCard interactive>
                    <div class="flex items-center justify-between">
                        <div class="font-display font-semibold t-ink">Simulasi TTX</div>
                        <span class="chip-brand">?</span>
                    </div>
                    <p class="text-sm mt-1" style="color: var(--muted)">Jalankan latihan tabletop.</p>
                </BaseCard>
            </Link>
            <Link :href="route('tenant.reports')">
                <BaseCard interactive>
                    <div class="flex items-center justify-between">
                        <div class="font-display font-semibold t-ink">Laporan</div>
                        <span class="chip-brand">?</span>
                    </div>
                    <p class="text-sm mt-1" style="color: var(--muted)">Skor awareness + ekspor CSV.</p>
                </BaseCard>
            </Link>
            <Link :href="route('tenant.billing.index')">
                <BaseCard interactive>
                    <div class="flex items-center justify-between">
                        <div class="font-display font-semibold t-ink">Billing</div>
                        <span class="chip-brand">?</span>
                    </div>
                    <p class="text-sm mt-1" style="color: var(--muted)">Package & langganan organisasi.</p>
                </BaseCard>
            </Link>
        </div>
    </AppLayout>
</template>