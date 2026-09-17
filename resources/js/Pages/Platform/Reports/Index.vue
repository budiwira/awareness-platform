<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ rows: { type: Array, default: () => [] }, platform_avg: Number });
</script>

<template>
    <Head title="Platform Reports" />

    <AppLayout title="Laporan Lintas Tenant">
        <div class="fade-in space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm t-muted">Ringkasan kesehatan awareness seluruh organisasi di platform.</p>
                    <p class="mt-1 text-xs t-muted">Gunakan tabel ini untuk membandingkan cakupan dan rata-rata awareness antar tenant.</p>
                </div>
                <div class="report-metric card">
                    <span class="text-xs font-semibold uppercase tracking-wide t-muted">Rata-rata platform</span>
                    <span class="font-display text-3xl t-ink">{{ platform_avg ?? '-' }}</span>
                    <BaseBadge variant="info" size="sm">Awareness</BaseBadge>
                </div>
            </header>

            <section class="card overflow-hidden" aria-labelledby="tenant-report-heading">
                <div class="flex flex-col gap-1 border-b b-line px-4 py-4 sm:px-6">
                    <h2 id="tenant-report-heading" class="font-display text-lg t-ink">Performa per organisasi</h2>
                    <p class="text-sm t-muted">{{ rows.length }} organisasi terdata</p>
                </div>
                <BaseTableContainer v-if="rows.length">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b b-line text-left text-xs uppercase tracking-wide t-muted">
                                <th class="px-4 py-3 font-semibold sm:px-6">Organisasi</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-6">Anggota</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-6">Penugasan</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-6">Completion</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-6">Awareness</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b b-line last:border-0">
                                <td class="px-4 py-4 sm:px-6">
                                    <span class="block max-w-[220px] truncate font-semibold t-ink" :title="row.name">{{ row.name }}</span>
                                    <span class="text-xs t-muted">Tenant {{ row.id }}</span>
                                </td>
                                <td class="px-4 py-4 text-right tabular-nums t-muted sm:px-6">{{ row.users }}</td>
                                <td class="px-4 py-4 text-right tabular-nums t-muted sm:px-6">{{ row.assignments }}</td>
                                <td class="px-4 py-4 text-right tabular-nums t-muted sm:px-6">{{ row.completion_rate }}%</td>
                                <td class="px-4 py-4 text-right sm:px-6">
                                    <BaseBadge :variant="row.avg_awareness >= 70 ? 'success' : 'danger'">
                                        {{ row.avg_awareness }}
                                    </BaseBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </BaseTableContainer>
                <EmptyState v-else title="Belum ada laporan tenant" message="Data laporan akan muncul setelah organisasi memiliki aktivitas." />
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.report-metric { display: grid; grid-template-columns: 1fr auto; gap: .25rem 1rem; min-width: min(100%, 220px); padding: 1rem 1.25rem; }
.report-metric .base-badge { grid-column: 1 / -1; justify-self: start; }
</style>