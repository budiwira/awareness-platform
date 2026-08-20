<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    tenants: Array,
});
</script>

<template>
    <Head title="Platform Dashboard" />

    <AppLayout title="Platform Dashboard">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard label="Total Tenants" :value="stats.tenants" accent="border-indigo-500" />
            <StatCard label="Active Tenants" :value="stats.active_tenants" accent="border-emerald-500" />
            <StatCard label="Total Users" :value="stats.users" accent="border-amber-500" />
            <StatCard label="Tenant Admins" :value="stats.tenant_admins" accent="border-sky-500" />
        </div>

        <div class="mt-8 bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 font-semibold text-gray-800">
                Organizations
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Users</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tenants" :key="t.slug" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ t.name }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ t.slug }}</td>
                        <td class="px-6 py-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="t.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ t.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ t.users_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>