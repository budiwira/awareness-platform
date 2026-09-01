<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ stats: Object, tenants: Array });

const statCards = [
    { key: 'tenants', label: 'Total Tenant', accent: 't-ink' },
    { key: 'active_tenants', label: 'Tenant Aktif', accent: 'chip-brand' },
    { key: 'users', label: 'Total User', accent: 't-ink' },
    { key: 'tenant_admins', label: 'Tenant Admin', accent: 'badge-warn' },
];
</script>

<template>
    <Head title="Platform Dashboard" />

    <AppLayout title="Dashboard Platform">
        <div
            class="rounded-2xl p-8 text-white mb-8"
            style="background: linear-gradient(140deg, #0f766e 0%, #115e59 55%, #134e4a 100%)"
        >
            <h2 class="font-display text-2xl font-bold mb-1">Kesehatan Platform</h2>
            <p class="text-teal-100 text-sm max-w-2xl">
                Ringkasan seluruh organisasi terdaftar: adopsi, aktivitas, dan distribusi pengguna.
            </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div v-for="s in statCards" :key="s.key" class="card p-5">
                <div class="text-xs uppercase tracking-wide" style="color: var(--muted)">{{ s.label }}</div>
                <div class="text-3xl font-bold mt-1" :class="s.accent">{{ stats[s.key] }}</div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-6 py-4 font-display font-semibold t-ink border-b b-line flex items-center justify-between">
                <span>Organisasi Terdaftar</span>
                <Link :href="route('platform.tenants.index')" class="text-sm chip-brand hover:underline">Kelola →</Link>
            </div>
            <table v-if="tenants.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b b-line" style="color: var(--muted)">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Anggota</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tenants" :key="t.slug" class="border-b b-line hover:bg-app/60 transition-colors">
                        <td class="px-6 py-3 font-medium t-ink">{{ t.name }}</td>
                        <td class="px-6 py-3 font-mono text-xs" style="color: var(--muted)">{{ t.slug }}</td>
                        <td class="px-6 py-3">
                            <span
                                class="badge"
                                :class="t.status === 'active' ? 'chip-brand chip-brand ' : 'bg-surface2 t-muted'"
                            >{{ t.status }}</span>
                        </td>
                        <td class="px-6 py-3 text-right font-medium t-ink">{{ t.users_count }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada tenant terdaftar." />
        </div>
    </AppLayout>
</template>