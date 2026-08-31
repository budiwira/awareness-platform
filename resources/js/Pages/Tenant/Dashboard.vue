<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ stats: Object });

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
                <div class="bg-white/10 rounded-xl px-5 py-3 text-center">
                    <div class="text-2xl font-bold">{{ stats.active_users }}<span class="text-teal-200 text-sm">/{{ stats.total_users }}</span></div>
                    <div class="text-[11px] text-teal-200">Anggota Aktif</div>
                </div>
                <div class="bg-white/10 rounded-xl px-5 py-3 text-center">
                    <div class="text-2xl font-bold">{{ stats.admins }}</div>
                    <div class="text-[11px] text-teal-200">Admin</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link :href="route('tenant.assignments.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Penugasan Training</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Tugaskan modul ke anggota.</p>
            </Link>
            <Link :href="route('tenant.ttx.exercises.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Simulasi TTX</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Jalankan latihan tabletop.</p>
            </Link>
            <Link :href="route('tenant.reports')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Laporan</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Skor awareness + ekspor CSV.</p>
            </Link>
            <Link :href="route('tenant.billing.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Billing</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Plan & langganan organisasi.</p>
            </Link>
        </div>
    </AppLayout>
</template>