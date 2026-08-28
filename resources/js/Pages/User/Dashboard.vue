<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ScoreRing from '@/Components/ScoreRing.vue';
import StatCard from '@/Components/StatCard.vue';

defineProps({ tenant_name: String, score: Object, pending: Number, in_progress: Number });
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard Saya">
        <p class="text-sm text-gray-500 mb-6">Selamat datang di {{ tenant_name ?? 'Awareness Platform' }}.</p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col items-center justify-center">
                <ScoreRing :value="score.overall" />
                <Link :href="route('user.score')" class="mt-3 text-sm text-indigo-600 hover:underline">
                    Lihat breakdown lengkap →
                </Link>
            </div>

            <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                <StatCard label="Tugas Menunggu" :value="pending" accent="text-amber-600" />
                <StatCard label="Sedang Dikerjakan" :value="in_progress" accent="text-blue-600" />
                <StatCard v-for="b in score.breakdown" :key="b.key" :label="b.label" :value="b.score" :accent="b.score >= 70 ? 'text-emerald-600' : 'text-gray-900'" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Link :href="route('user.training.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">📚 Training Saya</div>
                <div class="text-sm text-gray-500">Lihat modul yang ditugaskan dan lanjutkan progres.</div>
            </Link>
            <Link :href="route('user.cases.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">🎯 Case Study</div>
                <div class="text-sm text-gray-500">Latihan pengambilan keputusan berbasis skenario.</div>
            </Link>
            <Link :href="route('user.ctf.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">🚩 Capture The Flag</div>
                <div class="text-sm text-gray-500">Pecahkan tantangan dan kumpulkan poin.</div>
            </Link>
            <Link :href="route('notifications.index')" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="font-semibold text-gray-900 mb-1">🔔 Notifikasi</div>
                <div class="text-sm text-gray-500">Lihat penugasan baru dan undangan TTX.</div>
            </Link>
        </div>
    </AppLayout>
</template>