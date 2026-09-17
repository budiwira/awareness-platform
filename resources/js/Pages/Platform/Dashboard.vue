<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    top_tenants_by_risk: { type: Array, default: () => [] },
    plan_distribution: { type: Array, default: () => [] },
    phishing_adoption: { type: Object, required: true },
});

const numberFormatter = new Intl.NumberFormat('id-ID');
const inactiveTenants = computed(() => Math.max(0, Number(props.summary.total_tenants) - Number(props.summary.active_tenants)));
const phishingFollowUp = computed(() => Math.max(0, Number(props.phishing_adoption.tenants_with_phishing_feature) - Number(props.phishing_adoption.tenants_actively_sending)));
const formatNumber = value => numberFormatter.format(Number(value ?? 0));
const formatScore = value => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 1 });

const quickLinks = [
    { label: 'Kelola tenants', description: 'Status organisasi dan paket', route: 'platform.tenants.index' },
    { label: 'Kelola pengguna', description: 'Akses pengguna lintas organisasi', route: 'platform.users.index' },
    { label: 'Buka laporan', description: 'Analisis performa platform', route: 'platform.reports' },
    { label: 'Tinjau billing', description: 'Permintaan langganan masuk', route: 'platform.billing.requests' },
];
</script>

<template>
    <Head title="Ikhtisar Platform" />
    <AppLayout title="Ikhtisar Platform">
        <section class="hero-brand relative mb-8 overflow-hidden rounded-2xl p-6 shadow-lg sm:p-8 fade-in" aria-labelledby="dashboard-heading">
            <div class="relative z-10 max-w-3xl">
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] t-on-hero-muted">Pusat kendali platform</p>
                <h1 id="dashboard-heading" class="font-display text-3xl font-bold t-on-hero sm:text-4xl">Kesiapan siber seluruh organisasi, dalam satu pandangan</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 t-on-hero-muted">Pantau skala platform, kenali area yang membutuhkan perhatian, lalu lanjutkan ke ruang kerja operasional yang tepat.</p>
            </div>
            <svg class="pointer-events-none absolute -bottom-10 -right-8 h-56 w-56 opacity-20" aria-hidden="true" viewBox="0 0 200 200" fill="none">
                <circle cx="100" cy="100" r="72" stroke="currentColor" stroke-width="2" /><circle cx="100" cy="100" r="48" stroke="currentColor" stroke-width="2" /><path d="M100 28v144M28 100h144" stroke="currentColor" stroke-width="2" />
            </svg>
        </section>

        <section class="mb-8 fade-in" aria-labelledby="kpi-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div><h2 id="kpi-heading" class="font-display text-xl font-bold t-ink">Ringkasan platform</h2><p class="mt-1 text-sm t-muted">Indikator utama berdasarkan data operasional saat ini.</p></div>
                <Link :href="route('platform.reports')" class="dashboard-link hidden sm:inline-flex">Lihat laporan</Link>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="metric-card">
                    <div class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M9 14h.01M9 18h.01M15 10h.01M15 14h.01M15 18h.01" /></svg></div>
                    <div class="min-w-0"><p class="metric-label">Total organisasi</p><p class="metric-value">{{ formatNumber(summary.total_tenants) }}</p><p class="metric-note">Tenant terdaftar</p></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon metric-icon--success" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m5 12 4 4L19 6" /></svg></div>
                    <div class="min-w-0"><p class="metric-label">Organisasi aktif</p><p class="metric-value">{{ formatNumber(summary.active_tenants) }}</p><p class="metric-note">Memiliki langganan aktif</p></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg></div>
                    <div class="min-w-0"><p class="metric-label">Total pengguna</p><p class="metric-value">{{ formatNumber(summary.total_users) }}</p><p class="metric-note">Di seluruh organisasi</p></div>
                </article>
                <article class="metric-card">
                    <div class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2" /></svg></div>
                    <div class="min-w-0"><p class="metric-label">Rata-rata awareness</p><p class="metric-value">{{ formatScore(summary.avg_platform_awareness_score) }}</p><p class="metric-note">Skor penyelesaian gabungan</p></div>
                </article>
            </div>
        </section>

        <div class="mb-8 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(18rem,0.75fr)]">
            <section class="min-w-0 fade-in" aria-labelledby="risk-heading">
                <BaseTableContainer>
                    <div class="flex flex-col gap-3 border-b px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6" style="border-color: var(--line)">
                        <div><h2 id="risk-heading" class="font-display text-lg font-bold t-ink">Organisasi berisiko tertinggi</h2><p class="mt-1 text-xs t-muted">Urutan berdasarkan skor risiko platform saat ini.</p></div>
                        <Link :href="route('platform.tenants.index')" class="dashboard-link">Lihat tenants</Link>
                    </div>
                    <table v-if="top_tenants_by_risk.length" class="w-full text-sm">
                        <thead><tr class="border-b text-left t-muted" style="border-color: var(--line)"><th class="px-6 py-3">Organisasi</th><th class="px-6 py-3 text-right">Pengguna</th><th class="px-6 py-3 text-right">Penyelesaian</th><th class="px-6 py-3 text-right">Skor rata-rata</th><th class="px-6 py-3 text-right">Klik phishing</th><th class="px-6 py-3 text-right">Skor risiko</th><th class="px-6 py-3">Paket</th></tr></thead>
                        <tbody>
                            <tr v-for="(tenant, index) in top_tenants_by_risk" :key="tenant.tenant_id" class="border-b last:border-b-0" style="border-color: var(--line)">
                                <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="rank" :class="{ 'rank--first': index === 0 }">{{ index + 1 }}</span><span class="font-medium t-ink">{{ tenant.tenant_name }}</span></div></td>
                                <td class="px-6 py-4 text-right tabular-nums t-ink">{{ formatNumber(tenant.users_count) }}</td><td class="px-6 py-4 text-right tabular-nums t-ink">{{ formatScore(tenant.completion_rate) }}%</td><td class="px-6 py-4 text-right tabular-nums t-ink">{{ formatScore(tenant.avg_score) }}</td><td class="px-6 py-4 text-right font-semibold tabular-nums" style="color: var(--danger)">{{ formatNumber(tenant.phishing_clicked) }}</td><td class="px-6 py-4 text-right font-bold tabular-nums" style="color: var(--danger)">{{ formatScore(tenant.risk_score) }}</td><td class="px-6 py-4"><span class="badge chip-brand">{{ tenant.current_plan }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <EmptyState v-else title="Belum ada data risiko" message="Data akan muncul setelah organisasi memiliki aktivitas awareness." />
                </BaseTableContainer>
            </section>

            <aside class="card p-5 sm:p-6 fade-in" aria-labelledby="attention-heading">
                <h2 id="attention-heading" class="font-display text-lg font-bold t-ink">Perlu perhatian</h2><p class="mt-1 text-xs t-muted">Tindak lanjut dari indikator yang sudah tersedia.</p>
                <div class="mt-5 space-y-3">
                    <Link :href="route('platform.tenants.index')" class="attention-item"><span class="attention-value">{{ formatNumber(inactiveTenants) }}</span><span class="min-w-0 flex-1"><strong class="block text-sm t-ink">Organisasi belum aktif</strong><span class="mt-0.5 block text-xs t-muted">Tinjau status langganan tenant</span></span><svg class="h-4 w-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m9 18 6-6-6-6" /></svg></Link>
                    <Link :href="route('platform.reports')" class="attention-item"><span class="attention-value">{{ formatNumber(phishingFollowUp) }}</span><span class="min-w-0 flex-1"><strong class="block text-sm t-ink">Fitur phishing belum digunakan aktif</strong><span class="mt-0.5 block text-xs t-muted">Organisasi berfitur tanpa kampanye aktif</span></span><svg class="h-4 w-4 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m9 18 6-6-6-6" /></svg></Link>
                </div>
            </aside>
        </div>

        <section class="mb-8 fade-in" aria-labelledby="navigation-heading">
            <div class="mb-4"><h2 id="navigation-heading" class="font-display text-xl font-bold t-ink">Ruang kerja operasional</h2><p class="mt-1 text-sm t-muted">Lanjutkan ke area pengelolaan platform.</p></div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link v-for="item in quickLinks" :key="item.route" :href="route(item.route)" class="workspace-card"><span class="min-w-0"><strong class="block t-ink">{{ item.label }}</strong><span class="mt-1 block text-xs t-muted">{{ item.description }}</span></span><svg class="h-5 w-5 shrink-0" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12h14m-6-6 6 6-6 6" /></svg></Link>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 fade-in">
            <section class="card p-5 sm:p-6" aria-labelledby="package-heading">
                <h2 id="package-heading" class="font-display text-lg font-bold t-ink">Distribusi paket</h2><p class="mt-1 text-xs t-muted">Organisasi berdasarkan paket aktif.</p>
                <div v-if="plan_distribution.length" class="mt-5 space-y-3"><div v-for="item in plan_distribution" :key="item.plan_slug" class="flex items-center justify-between gap-4 rounded-xl bg-surface2 px-4 py-3"><span class="flex min-w-0 items-center gap-3"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: var(--brand)"></span><span class="truncate text-sm t-ink">{{ item.plan_name }}</span></span><span class="shrink-0 text-right text-sm font-semibold tabular-nums t-ink">{{ formatNumber(item.tenant_count) }} organisasi</span></div></div>
                <EmptyState v-else title="Belum ada distribusi paket" message="Distribusi akan tampil setelah organisasi memiliki paket aktif." />
            </section>
            <section class="card p-5 sm:p-6" aria-labelledby="phishing-heading">
                <h2 id="phishing-heading" class="font-display text-lg font-bold t-ink">Adopsi simulasi phishing</h2><p class="mt-1 text-xs t-muted">Penggunaan fitur simulasi oleh organisasi.</p>
                <dl class="mt-5 divide-y" style="border-color: var(--line)"><div class="flex items-center justify-between gap-4 py-3 first:pt-0" style="border-color: var(--line)"><dt class="text-sm t-muted">Memiliki fitur phishing</dt><dd class="text-right font-display text-2xl font-bold tabular-nums t-ink">{{ formatNumber(phishing_adoption.tenants_with_phishing_feature) }}</dd></div><div class="flex items-center justify-between gap-4 py-3" style="border-color: var(--line)"><dt class="text-sm t-muted">Aktif mengirim kampanye</dt><dd class="text-right font-display text-2xl font-bold tabular-nums" style="color: var(--ok)">{{ formatNumber(phishing_adoption.tenants_actively_sending) }}</dd></div><div class="flex items-center justify-between gap-4 py-3 last:pb-0" style="border-color: var(--line)"><dt class="text-sm t-muted">Total kampanye terkirim</dt><dd class="text-right font-display text-2xl font-bold tabular-nums t-ink">{{ formatNumber(phishing_adoption.total_campaigns_sent) }}</dd></div></dl>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.metric-card { display:flex;min-width:0;align-items:flex-start;gap:var(--sp-4);border:1px solid var(--line);border-radius:var(--r-card);background:var(--surface);padding:var(--sp-5);box-shadow:var(--shadow-sm) }
.metric-icon { display:grid;width:2.75rem;height:2.75rem;flex:0 0 auto;place-items:center;border-radius:var(--r-lg);background:var(--brand-soft);color:var(--brand) }.metric-icon--success{background:var(--ok-bg);color:var(--ok)}.metric-icon svg{width:1.35rem;height:1.35rem;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.metric-label{color:var(--muted);font-size:.75rem}.metric-value{margin-top:.15rem;color:var(--ink);font-family:var(--font-sans);font-size:2rem;font-weight:700;line-height:1.1;letter-spacing:-.03em}.metric-note{margin-top:.4rem;color:var(--muted);font-size:.7rem}
.dashboard-link{align-items:center;justify-content:center;border-radius:var(--r-full);color:var(--brand);font-size:.75rem;font-weight:600;transition:color var(--dur-fast) var(--ease),transform var(--dur-fast) var(--ease)}.dashboard-link:hover{color:var(--brand-strong)}.dashboard-link:active{transform:translateY(1px)}
.rank{display:grid;width:1.75rem;height:1.75rem;flex:0 0 auto;place-items:center;border-radius:var(--r-full);background:var(--surface-2);color:var(--muted);font-size:.7rem;font-weight:700}.rank--first{background:var(--danger-bg);color:var(--danger)}
.attention-item,.workspace-card{display:flex;align-items:center;gap:var(--sp-3);border:1px solid var(--line);border-radius:var(--r-lg);color:var(--muted);transition:border-color var(--dur) var(--ease),background-color var(--dur) var(--ease),color var(--dur) var(--ease),transform var(--dur-fast) var(--ease),box-shadow var(--dur) var(--ease)}.attention-item{padding:var(--sp-3)}.workspace-card{justify-content:space-between;padding:var(--sp-5);background:var(--surface);box-shadow:var(--shadow-sm)}.attention-item:hover,.workspace-card:hover{border-color:var(--brand);background:var(--surface-2);color:var(--brand);box-shadow:var(--shadow-md)}.attention-item:active,.workspace-card:active{transform:translateY(1px)}.attention-value{display:grid;min-width:2.5rem;height:2.5rem;place-items:center;border-radius:var(--r-lg);background:var(--warn-bg);color:var(--warn);font-weight:700;font-variant-numeric:tabular-nums}
@media (prefers-reduced-motion:reduce){.dashboard-link,.attention-item,.workspace-card{transition:none}}
</style>
