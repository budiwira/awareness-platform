<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ rows: Array, platform_avg: Number });
</script>

<template>
    <Head title="Platform Reports" />

    <AppLayout title="Laporan Lintas Tenant">
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm t-muted">Kesehatan awareness seluruh organisasi di platform.</p>
            <div class="card w-full px-5 py-3 sm:w-auto" style="border-color: var(--brand)">
                <span class="text-xs uppercase tracking-wide" style="color: var(--brand-strong)">Awareness Platform</span>
                <span class="ml-3 text-2xl font-bold" style="color: var(--brand)">{{ platform_avg }}</span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Organisasi</th>
                        <th class="px-6 py-3 font-medium">Anggota</th>
                        <th class="px-6 py-3 font-medium">Penugasan</th>
                        <th class="px-6 py-3 font-medium">Completion</th>
                        <th class="px-6 py-3 font-medium">Rata-rata Awareness</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-b b-line">
                        <td class="px-6 py-3 font-medium t-ink">{{ row.name }}</td>
                        <td class="px-6 py-3 t-muted">{{ row.users }}</td>
                        <td class="px-6 py-3 t-muted">{{ row.assignments }}</td>
                        <td class="px-6 py-3 t-muted">{{ row.completion_rate }}%</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                                  :class="row.avg_awareness >= 70 ? 'badge-ok' : 'badge-danger'">
                                {{ row.avg_awareness }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center t-muted">Belum ada tenant.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
