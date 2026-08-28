<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ stats: Object, tenants: Array });
</script>

<template>
    <Head title="Platform Dashboard" />

    <AppLayout title="Dashboard Platform">
        <p class="text-sm text-gray-500 mb-6">Kesehatan seluruh organisasi di platform.</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <StatCard label="Total Tenant" :value="stats.tenants" />
            <StatCard label="Tenant Aktif" :value="stats.active_tenants" accent="text-emerald-600" />
            <StatCard label="Total User" :value="stats.users" />
            <StatCard label="Tenant Admin" :value="stats.tenant_admins" accent="text-indigo-600" />
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 font-semibold text-gray-800 border-b border-gray-100 flex items-center justify-between">
                <span>Organisasi Terdaftar</span>
                <Link :href="route('platform.tenants.index')" class="text-sm text-indigo-600 hover:underline">Kelola →</Link>
            </div>
            <table v-if="tenants.length > 0" class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Anggota</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tenants" :key="t.slug" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ t.name }}</td>
                        <td class="px-6 py-3 text-gray-500 font-mono text-xs">{{ t.slug }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="t.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                                {{ t.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ t.users_count }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else message="Belum ada tenant terdaftar." />
        </div>
    </AppLayout>
</template>