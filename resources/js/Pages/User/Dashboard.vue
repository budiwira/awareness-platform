<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ tenant_name: String });
</script>

<template>
    <Head title="My Dashboard" />

    <AppLayout title="My Dashboard">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow-sm p-8 text-white mb-8">
            <h2 class="text-2xl font-bold">Selamat datang! 👋</h2>
            <p class="text-indigo-100 mt-2 text-sm">
                Platform awareness security organisasi Anda
                <span class="font-semibold text-white">({{ tenant_name }})</span>.
                Mulai bangun kebiasaan keamanan digital Anda hari ini.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <StatCard label="Awareness Score" value="—" hint="engine segera aktif" accent="border-indigo-500" />
            <StatCard label="Training Completed" value="0" hint="menunggu modul" accent="border-emerald-500" />
            <StatCard label="Quiz Best Score" value="—" hint="menunggu modul" accent="border-amber-500" />
            <StatCard label="CTF Flags" value="0" hint="menunggu modul" accent="border-sky-500" />
        </div>

        <div class="mt-8 bg-white rounded-xl shadow-sm p-6">
            <div class="font-semibold text-gray-800 mb-4">Getting Started</div>
            <ul class="space-y-3 text-sm">
                <li class="flex items-center gap-3">
                    <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">✓</span>
                    Akun aktif & terverifikasi admin
                </li>
                <li class="flex items-center gap-3 text-gray-400">
                    <span class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center text-xs">○</span>
                    Selesaikan training pertama Anda
                </li>
                <li class="flex items-center gap-3 text-gray-400">
                    <span class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center text-xs">○</span>
                    Ikuti quiz assessment awal
                </li>
                <li class="flex items-center gap-3 text-gray-400">
                    <span class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center text-xs">○</span>
                    Coba challenge CTF pertama
                </li>
            </ul>
        </div>
    </AppLayout>
</template>