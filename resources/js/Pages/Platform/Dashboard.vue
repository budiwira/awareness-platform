<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ 
    summary: Object,
    top_tenants_by_risk: Array,
    plan_distribution: Array,
    phishing_adoption: Object,
});
</script>

<template>
    <Head title="Platform Analytics" />

    <AppLayout title="Platform Analytics">
        <!-- Platform Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Total Tenants</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.total_tenants }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Active Tenants</div>
                <div class="font-display text-4xl font-bold" style="color: var(--ok)">{{ summary.active_tenants }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Total Users</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.total_users }}</div>
            </div>
            <div class="card p-6">
                <div class="text-xs mb-2 t-muted">Avg Platform Score</div>
                <div class="font-display text-4xl font-bold t-ink">{{ summary.avg_platform_awareness_score }}</div>
            </div>
        </div>

        <!-- Top Tenants by Risk -->
        <div class="card overflow-hidden mb-8">
            <div class="px-6 py-4 border-b" style="border-color: var(--line)">
                <div class="font-semibold t-ink">Top 5 Tenants by Risk</div>
                <div class="text-xs t-muted mt-1">Tenant dengan risk score tertinggi (memerlukan perhatian)</div>
            </div>
            <table v-if="top_tenants_by_risk.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b t-muted" style="border-color: var(--line)">
                        <th class="px-6 py-3 font-medium">Tenant</th>
                        <th class="px-6 py-3 font-medium text-right">Users</th>
                        <th class="px-6 py-3 font-medium text-right">Completion</th>
                        <th class="px-6 py-3 font-medium text-right">Avg Score</th>
                        <th class="px-6 py-3 font-medium text-right">Phishing Clicked</th>
                        <th class="px-6 py-3 font-medium text-right">Risk Score</th>
                        <th class="px-6 py-3 font-medium">Plan</th>
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
                Tidak ada data tenant
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Plan Distribution -->
            <div class="card p-6">
                <div class="font-semibold mb-4 t-ink">Plan Distribution</div>
                <div class="space-y-3">
                    <div v-for="plan in plan_distribution" :key="plan.plan_slug" class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full" style="background: var(--brand)"></div>
                            <span class="text-sm t-ink">{{ plan.plan_name }}</span>
                        </div>
                        <span class="font-semibold t-ink">{{ plan.tenant_count }} tenant</span>
                    </div>
                </div>
            </div>

            <!-- Phishing Adoption -->
            <div class="card p-6">
                <div class="font-semibold mb-4 t-ink">Phishing Simulation Adoption</div>
                <div class="space-y-4">
                    <div>
                        <div class="text-xs mb-1 t-muted">Tenants with Phishing Feature</div>
                        <div class="font-display text-3xl font-bold t-ink">{{ phishing_adoption.tenants_with_phishing_feature }}</div>
                    </div>
                    <div>
                        <div class="text-xs mb-1 t-muted">Tenants Actively Sending Campaigns</div>
                        <div class="font-display text-3xl font-bold" style="color: var(--ok)">{{ phishing_adoption.tenants_actively_sending }}</div>
                    </div>
                    <div>
                        <div class="text-xs mb-1 t-muted">Total Campaigns Sent</div>
                        <div class="font-display text-3xl font-bold t-ink">{{ phishing_adoption.total_campaigns_sent }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
