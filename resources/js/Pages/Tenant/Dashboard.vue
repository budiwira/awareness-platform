<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';

defineProps({ stats: Object });
</script>

<template>
    <Head title="Tenant Dashboard" />

    <AppLayout title="Dashboard Organisasi">
        <p class="text-sm text-gray-500 mb-6">Ringkasan aktivitas & kesiapan anggota organisasi.</p>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <StatCard label="Total Anggota" :value="stats.total_users" />
            <StatCard label="Anggota Aktif" :value="stats.active_users" accent="text-emerald-600" />
            <StatCard label="Admin" :value="stats.admins" accent="text-indigo-600" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link :href="route('tenant.assignments.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">📋 Training</div>
                <div class="text-sm text-gray-500">Kelola penugasan modul ke anggota.</div>
            </Link>
            <Link :href="route('tenant.ttx.exercises.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">🎯 Simulasi TTX</div>
                <div class="text-sm text-gray-500">Buat & jalankan latihan tabletop.</div>
            </Link>
            <Link :href="route('tenant.reports')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">📊 Laporan</div>
                <div class="text-sm text-gray-500">Lihat awareness score & ekspor CSV.</div>
            </Link>
        </div>
    </AppLayout>
</template>