<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ stats: Object });

const tierPercent = (count) => {
    const total = props.stats.tier_baik + props.stats.tier_cukup + props.stats.tier_perlu_perbaikan + props.stats.tier_belum_mengerjakan;
    return total > 0 ? (count / total * 100).toFixed(1) : 0;
};

const tenantName = computed(() => usePage().props.auth?.user?.tenant_name ?? 'Organisasi');
</script>

<template>
    <Head title="Tenant Dashboard" />

    <AppLayout title="Dashboard Organisasi">
        <div
            class="rounded-2xl p-8 text-white mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6"
            style="background: linear-gradient(140deg, #0f766e 0%, #115e59 55%, #134e4a 100%)"
        >
            <div>
                <div class="text-teal-200 text-sm mb-1">Organisasi</div>
                <h2 class="font-display text-2xl font-bold">{{ tenantName }}</h2>
                <p class="text-teal-100 text-sm mt-2 max-w-xl">
                    Pantau kesiapan keamanan anggota: tugaskan training, jalankan simulasi TTX,
                    dan ukur hasilnya lewat laporan awareness.
                </p>
            </div>
            <div class="flex gap-4">
                <div class="bg-surface/10 rounded-xl px-5 py-3 text-center">
                    <div class="text-2xl font-bold">{{ stats.active_users }}<span class="text-teal-200 text-sm">/{{ stats.total_users }}</span></div>
                    <div class="text-[11px] text-teal-200">Anggota Aktif</div>
                </div>
                <div class="bg-surface/10 rounded-xl px-5 py-3 text-center">
                    <div class="text-2xl font-bold">{{ stats.admins }}</div>
                    <div class="text-[11px] text-teal-200">Admin</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2" style="color: var(--muted)">Rata-rata Skor Kesadaran</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ink)">{{ stats.avg_awareness_score }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2" style="color: var(--muted)">Tingkat Penyelesaian</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ink)">{{ stats.completion_rate }}%</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-3" style="color: var(--muted)">Distribusi Kesadaran</div>
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
            </div>
        </div>

        <div class="card p-6 mb-8">
            <div class="text-xs mb-3" style="color: var(--muted)">Distribusi Visual</div>
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
                    <span style="color: var(--muted)">Belum Mengerjakan (0)</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link :href="route('tenant.assignments.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold t-ink">Penugasan Training</div>
                    <span class="chip-brand group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Tugaskan modul ke anggota.</p>
            </Link>
            <Link :href="route('tenant.ttx.exercises.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold t-ink">Simulasi TTX</div>
                    <span class="chip-brand group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Jalankan latihan tabletop.</p>
            </Link>
            <Link :href="route('tenant.reports')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold t-ink">Laporan</div>
                    <span class="chip-brand group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Skor awareness + ekspor CSV.</p>
            </Link>
            <Link :href="route('tenant.billing.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold t-ink">Billing</div>
                    <span class="chip-brand group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Plan & langganan organisasi.</p>
            </Link>
        </div>
    </AppLayout>
</template>