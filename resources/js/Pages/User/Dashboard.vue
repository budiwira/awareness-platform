<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ScoreRing from '@/Components/ScoreRing.vue';

defineProps({ tenant_name: String, score: Object, pending: Number, in_progress: Number });

const userName = computed(() => usePage().props.auth?.user?.name ?? '');
const barColor = (v) => (v >= 70 ? '#0f766e' : v >= 40 ? '#f59e0b' : '#e11d48');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard Saya">
        <!-- Hero -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div
                class="lg:col-span-2 rounded-2xl p-8 text-white flex flex-col justify-between"
                style="background: linear-gradient(140deg, #0f766e 0%, #115e59 55%, #134e4a 100%)"
            >
                <div>
                    <div class="text-teal-200 text-sm mb-2">{{ tenant_name ?? 'Awareness Platform' }}</div>
                    <h2 class="font-display text-3xl font-bold leading-tight mb-3">Halo, {{ userName }}.</h2>
                    <p class="text-teal-100 max-w-lg">
                        Tingkatkan kesadaran keamanan Anda melalui training, case study, dan CTF.
                        Skor diperbarui otomatis dari lima komponen.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3 mt-6">
                    <Link :href="route('user.training.index')" class="btn bg-white text-teal-800 hover:bg-teal-50">
                        Lanjutkan Training
                    </Link>
                    <Link :href="route('user.score')" class="btn bg-white/10 text-white hover:bg-white/20">
                        Lihat Breakdown
                    </Link>
                </div>
            </div>

            <div class="card p-6 flex flex-col items-center justify-center">
                <ScoreRing :value="score.overall" />
                <div class="mt-4 grid grid-cols-2 gap-3 w-full text-center">
                    <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                        <div class="text-lg font-bold text-amber-700">{{ pending }}</div>
                        <div class="text-[11px] text-amber-700">Menunggu</div>
                    </div>
                    <div class="rounded-lg bg-teal-50 border border-teal-200 px-3 py-2">
                        <div class="text-lg font-bold text-teal-700">{{ in_progress }}</div>
                        <div class="text-[11px] text-teal-700">Berjalan</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breakdown -->
        <h3 class="font-display text-sm font-semibold uppercase tracking-wider mb-3" style="color: var(--muted)">
            Komponen Skor
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div v-for="b in score.breakdown" :key="b.key" class="card p-4">
                <div class="text-xs mb-1" style="color: var(--muted)">{{ b.label }}</div>
                <div class="text-xl font-bold text-gray-900 mb-2">{{ b.score }}</div>
                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full" :style="{ width: b.score + '%', background: barColor(b.score) }"></div>
                </div>
            </div>
        </div>

        <!-- Quick access -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link :href="route('user.cases.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Case Study</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Latihan keputusan berbasis skenario.</p>
            </Link>
            <Link :href="route('user.ctf.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Capture The Flag</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Pecahkan tantangan, kumpulkan poin.</p>
            </Link>
            <Link :href="route('notifications.index')" class="card p-6 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <div class="font-display font-semibold text-gray-900">Notifikasi</div>
                    <span class="text-teal-600 group-hover:translate-x-1 transition">→</span>
                </div>
                <p class="text-sm mt-1" style="color: var(--muted)">Penugasan baru dan undangan TTX.</p>
            </Link>
        </div>
    </AppLayout>
</template>