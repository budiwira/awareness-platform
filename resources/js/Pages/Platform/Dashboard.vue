<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';

const props = defineProps({ 
    summary: Object,
    top_tenants_by_risk: Array,
    plan_distribution: Array,
    phishing_adoption: Object,
});
</script>

<template>
    <Head title="Ikhtisar Platform" />

    <AppLayout title="Ikhtisar Platform">
        <div class="mb-8 fade-in">
            <p class="text-sm font-medium chip-brand inline-flex mb-3">Operasional platform</p>
            <h1 class="font-display text-3xl font-bold t-ink">Pantau kesehatan seluruh organisasi</h1>
            <p class="text-sm t-muted mt-2 max-w-2xl">Ringkasan tenant, pengguna, dan aktivitas simulasi phishing untuk membantu operator menentukan prioritas tindak lanjut.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 fade-in">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Total organisasi</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.total_tenants }}</div>
                <div class="text-xs t-muted mt-2">Tenant terdaftar di platform</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Organisasi aktif</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ok)">{{ summary.active_tenants }}</div>
                <div class="text-xs t-muted mt-2">Siap digunakan saat ini</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Total pengguna</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.total_users }}</div>
                <div class="text-xs t-muted mt-2">Pengguna di seluruh organisasi</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Rata-rata skor platform</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.avg_platform_awareness_score }}</div>
                <div class="text-xs t-muted mt-2">Skor awareness gabungan</div>
            </div>
        </div>

        <div class="mb-8 min-w-0">
            <BaseTableContainer>
            <div class="px-6 py-4 border-b" style="border-color: var(--line)">
                <div class="font-semibold t-ink">5 organisasi dengan risiko tertinggi</div>
                <div class="text-xs t-muted mt-1">Prioritas pemantauan berdasarkan skor risiko saat ini.</div>
            </div>
            <table v-if="top_tenants_by_risk.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Organisasi</th>
                        <th class="px-6 py-3 font-medium text-right">Pengguna</th>
                        <th class="px-6 py-3 font-medium text-right">Penyelesaian</th>
                        <th class="px-6 py-3 font-medium text-right">Skor rata-rata</th>
                        <th class="px-6 py-3 font-medium text-right">Klik phishing</th>
                        <th class="px-6 py-3 font-medium text-right">Skor risiko</th>
                        <th class="px-6 py-3 font-medium">Paket</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(tenant, idx) in top_tenants_by_risk" :key="tenant.tenant_id" class="border-b" style="border-color: var(--line)">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" 
                                     :style="{ background: idx === 0 ? 'var(--danger-bg)' : 'var(--surface-2)', color: idx === 0 ? 'var(--danger)' : 'var(--muted)' }">
                                    {{ idx + 1 }}
                                </div>
                                <span class="font-medium t-ink">{{ tenant.tenant_name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-right t-ink">{{ tenant.users_count }}</td>
                        <td class="px-6 py-3 text-right t-ink">{{ tenant.completion_rate }}%</td>
                        <td class="px-6 py-3 text-right t-ink">{{ tenant.avg_score }}</td>
                        <td class="px-6 py-3 text-right font-semibold" style="color: var(--danger)">{{ tenant.phishing_clicked }}</td>
                        <td class="px-6 py-3 text-right font-bold" style="color: var(--danger)">{{ tenant.risk_score }}</td>
                        <td class="px-6 py-3">
                            <span class="badge" style="background: var(--brand-soft); color: var(--brand)">{{ tenant.current_plan }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-6 py-12 text-center t-muted">
                <div class="font-medium t-ink">Belum ada data risiko organisasi</div>
                <div class="text-xs mt-1">Data akan muncul setelah organisasi memiliki aktivitas awareness.</div>
            </div>
            </BaseTableContainer>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <div class="font-semibold t-ink">Distribusi paket</div>
                <div class="text-xs t-muted mt-1 mb-4">Jumlah organisasi berdasarkan paket aktif.</div>
                <div v-if="plan_distribution.length > 0" class="space-y-3">
                    <div v-for="Package in plan_distribution" :key="Package.plan_slug" class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full" style="background: var(--brand)"></div>
                            <span class="text-sm t-ink">{{ Package.plan_name }}</span>
                        </div>
                        <span class="font-semibold t-ink">{{ Package.tenant_count }} organisasi</span>
                    </div>
                </div>
                <div v-else class="text-sm t-muted py-5">Belum ada distribusi paket untuk ditampilkan.</div>
            </div>

            <div class="card p-6">
                <div class="font-semibold t-ink">Adopsi simulasi phishing</div>
                <div class="text-xs t-muted mt-1 mb-4">Gambaran penggunaan fitur simulasi oleh organisasi.</div>
                <div class="space-y-4">
                    <div>
                        <div class="text-xs mb-1 t-muted">Organisasi dengan fitur phishing</div>
                        <div class="font-display text-3xl font-bold t-ink">{{ phishing_adoption.tenants_with_phishing_feature }}</div>
                    </div>
                    <div>
                        <div class="text-xs mb-1 t-muted">Organisasi aktif mengirim kampanye</div>
                        <div class="font-display text-3xl font-bold" style="color: var(--ok)">{{ phishing_adoption.tenants_actively_sending }}</div>
                    </div>
                    <div>
                        <div class="text-xs mb-1 t-muted">Total kampanye terkirim</div>
                        <div class="font-display text-3xl font-bold t-ink">{{ phishing_adoption.total_campaigns_sent }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
