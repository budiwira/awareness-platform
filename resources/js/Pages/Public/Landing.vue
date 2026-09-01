<script setup>
import { ref, onMounted, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const theme = ref('dark');

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;
    localStorage.setItem('theme', theme.value);
};

onMounted(() => {
    const saved = localStorage.getItem('theme') || 'dark';
    theme.value = saved;
    document.documentElement.dataset.theme = saved;
});

const isAuthenticated = computed(() => !!page.props.auth.user);
const ctaText = computed(() => isAuthenticated.value ? 'Masuk Dashboard' : 'Masuk ke Platform');
const ctaHref = computed(() => isAuthenticated.value ? '/dashboard' : '/login');

const scrollTo = (id) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <Head title="Security Awareness Platform" />

    <div class="min-h-screen bg-app">
        <!-- Navbar -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-surface b-line border-b backdrop-blur-sm" style="background: var(--header)">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-display font-bold text-sm" style="background: rgba(124,58,237,.2); color: var(--brand-strong)">SA</div>
                        <span class="font-display font-semibold text-base t-ink">Awareness Platform</span>
                    </div>

                    <div class="hidden md:flex items-center gap-6 text-sm">
                        <button @click="scrollTo('features')" class="t-muted transition-colors" style="cursor: pointer" @mouseenter="$event.currentTarget.style.color = 'var(--ink)'" @mouseleave="$event.currentTarget.style.color = ''">
                            Fitur
                        </button>
                        <button @click="scrollTo('how-it-works')" class="t-muted transition-colors" style="cursor: pointer" @mouseenter="$event.currentTarget.style.color = 'var(--ink)'" @mouseleave="$event.currentTarget.style.color = ''">
                            Cara Kerja
                        </button>
                        <button @click="scrollTo('plans')" class="t-muted transition-colors" style="cursor: pointer" @mouseenter="$event.currentTarget.style.color = 'var(--ink)'" @mouseleave="$event.currentTarget.style.color = ''">
                            Paket
                        </button>
                        <button @click="scrollTo('security')" class="t-muted transition-colors" style="cursor: pointer" @mouseenter="$event.currentTarget.style.color = 'var(--ink)'" @mouseleave="$event.currentTarget.style.color = ''">
                            Keamanan
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            @click="toggleTheme"
                            class="transition-colors t-muted"
                            :aria-label="'Ganti tema'"
                            title="Ganti tema"
                            @mouseenter="$event.currentTarget.style.color = 'var(--ink)'"
                            @mouseleave="$event.currentTarget.style.color = ''"
                        >
                            <svg v-if="theme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>

                        <Link :href="ctaHref" class="btn btn-primary">
                            {{ ctaText }}
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero -->
        <section class="pt-32 pb-20 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div class="fade-in">
                        <h1 class="font-display text-5xl lg:text-6xl font-bold leading-tight t-ink mb-6">
                            Bangun Budaya Keamanan Siber yang Terukur
                        </h1>
                        <p class="text-lg t-muted mb-8 max-w-xl">
                            Platform awareness untuk melatih karyawan, mengukur risiko, dan membantu organisasi meningkatkan kesiapan keamanan.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <Link :href="ctaHref" class="btn btn-primary">
                                {{ ctaText }}
                            </Link>
                            <button @click="scrollTo('features')" class="btn btn-secondary">
                                Lihat Fitur
                            </button>
                        </div>
                    </div>

                    <div class="fade-in">
                        <div class="card p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 b-line border-b">
                                <span class="text-sm font-semibold t-muted">Dashboard Metrik</span>
                                <span class="w-2 h-2 rounded-full" style="background: var(--ok)"></span>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-center">
                                    <div class="text-3xl font-display font-bold" style="color: var(--brand)">78</div>
                                    <div class="text-xs t-muted mt-1">Awareness Score</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-display font-bold" style="color: var(--ok)">92%</div>
                                    <div class="text-xs t-muted mt-1">Training Completion</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-display font-bold" style="color: var(--warn)">Cukup</div>
                                    <div class="text-xs t-muted mt-1">Risk Tier</div>
                                </div>
                            </div>
                            <div class="pt-3 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="t-muted">Training Modules</span>
                                    <span class="t-ink font-semibold">8/10</span>
                                </div>
                                <div class="h-2 rounded-full" style="background: var(--surface-2)">
                                    <div class="h-2 rounded-full transition-all" style="width: 80%; background: var(--brand)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Metrics Strip -->
        <section class="py-12 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="card p-6 text-center fade-in">
                        <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">CBT Interaktif</h3>
                        <p class="text-sm t-muted">Quiz berbasis skenario dengan timer dan feedback langsung</p>
                    </div>

                    <div class="card p-6 text-center fade-in" style="animation-delay: 0.1s">
                        <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Plan & Entitlements</h3>
                        <p class="text-sm t-muted">Paket fleksibel dengan gating fitur berbasis langganan</p>
                    </div>

                    <div class="card p-6 text-center fade-in" style="animation-delay: 0.2s">
                        <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Tenant Analytics</h3>
                        <p class="text-sm t-muted">Laporan dan awareness score 5 komponen yang transparan</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="beam max-w-7xl mx-auto"></div>

        <!-- Features -->
        <section id="features" class="py-20 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-display text-4xl font-bold t-ink mb-4">Fitur Utama Platform</h2>
                    <p class="text-lg t-muted max-w-2xl mx-auto">
                        Modul lengkap untuk membangun awareness keamanan siber yang terstruktur dan terukur
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="card p-6 fade-in">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Training Modules</h3>
                        <p class="text-sm t-muted">Konten kurasi berbasis skenario dunia nyata untuk meningkatkan awareness</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.05s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">CBT Quiz dengan Timer</h3>
                        <p class="text-sm t-muted">Computer-Based Test dengan batas waktu dan scoring server-side</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.1s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Tabletop Exercise</h3>
                        <p class="text-sm t-muted">Simulasi 4 fase respons insiden untuk melatih tim keamanan</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.15s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Case Study</h3>
                        <p class="text-sm t-muted">Studi kasus interaktif dengan keputusan bercabang dan feedback kontekstual</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.2s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">CTF Challenge</h3>
                        <p class="text-sm t-muted">Capture The Flag untuk praktisi keamanan tingkat lanjut</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.25s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Reports & Awareness Score</h3>
                        <p class="text-sm t-muted">Laporan ekspor dan skor awareness 5 komponen yang transparan</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="beam max-w-7xl mx-auto"></div>

        <!-- How it Works -->
        <section id="how-it-works" class="py-20 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-display text-4xl font-bold t-ink mb-4">Cara Kerja</h2>
                    <p class="text-lg t-muted max-w-2xl mx-auto">
                        Alur lengkap dari setup hingga monitoring awareness keamanan organisasi
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center fade-in">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center font-display text-2xl font-bold" style="background: var(--brand-soft); color: var(--brand)">1</div>
                        <h3 class="font-display font-bold t-ink mb-2">Setup Platform</h3>
                        <p class="text-sm t-muted">Super admin mengatur plan, konten, dan entitlements</p>
                    </div>

                    <div class="text-center fade-in" style="animation-delay: 0.1s">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center font-display text-2xl font-bold" style="background: var(--brand-soft); color: var(--brand)">2</div>
                        <h3 class="font-display font-bold t-ink mb-2">Assign Training</h3>
                        <p class="text-sm t-muted">Tenant admin menugaskan modul pelatihan ke user</p>
                    </div>

                    <div class="text-center fade-in" style="animation-delay: 0.2s">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center font-display text-2xl font-bold" style="background: var(--brand-soft); color: var(--brand)">3</div>
                        <h3 class="font-display font-bold t-ink mb-2">Complete Modules</h3>
                        <p class="text-sm t-muted">User mengikuti training, quiz, case study, dan TTX</p>
                    </div>

                    <div class="text-center fade-in" style="animation-delay: 0.3s">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center font-display text-2xl font-bold" style="background: var(--brand-soft); color: var(--brand)">4</div>
                        <h3 class="font-display font-bold t-ink mb-2">Monitor & Report</h3>
                        <p class="text-sm t-muted">Organisasi memantau skor awareness dan risk tier</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="beam max-w-7xl mx-auto"></div>

        <!-- Plans -->
        <section id="plans" class="py-20 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-display text-4xl font-bold t-ink mb-4">Paket Langganan</h2>
                    <p class="text-lg t-muted max-w-2xl mx-auto">
                        Pilih paket yang sesuai dengan kebutuhan organisasi Anda
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="card p-6 fade-in">
                        <h3 class="font-display font-bold text-xl t-ink mb-2">Starter</h3>
                        <p class="text-sm t-muted mb-6">Training dasar dengan modul kurasi</p>
                        <ul class="space-y-2 text-sm t-muted mb-6">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Training modules</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>CBT quiz dasar</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Awareness score</span>
                            </li>
                        </ul>
                        <button class="btn btn-secondary w-full">Hubungi Admin</button>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.1s">
                        <h3 class="font-display font-bold text-xl t-ink mb-2">Pro</h3>
                        <p class="text-sm t-muted mb-6">Semua modul dan reports export</p>
                        <ul class="space-y-2 text-sm t-muted mb-6">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Semua fitur Starter</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Case studies</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Tabletop exercise</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Reports export</span>
                            </li>
                        </ul>
                        <button class="btn btn-primary w-full">Ajukan Upgrade</button>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.2s">
                        <h3 class="font-display font-bold text-xl t-ink mb-2">Enterprise</h3>
                        <p class="text-sm t-muted mb-6">Semua fitur termasuk CTF</p>
                        <ul class="space-y-2 text-sm t-muted mb-6">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Semua fitur Pro</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>CTF challenges</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Advanced analytics</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Priority support</span>
                            </li>
                        </ul>
                        <button class="btn btn-primary w-full">Ajukan Upgrade</button>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.3s">
                        <h3 class="font-display font-bold text-xl t-ink mb-2">Custom</h3>
                        <p class="text-sm t-muted mb-6">Fitur dan modul fleksibel</p>
                        <ul class="space-y-2 text-sm t-muted mb-6">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Custom modules</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Fitur pilihan</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Dedicated support</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" style="color: var(--ok)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>On-premise option</span>
                            </li>
                        </ul>
                        <button class="btn btn-secondary w-full">Hubungi Admin</button>
                    </div>
                </div>
            </div>
        </section>

        <div class="beam max-w-7xl mx-auto"></div>

        <!-- Security & Ethics -->
        <section id="security" class="py-20 px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-display text-4xl font-bold t-ink mb-4">Keamanan & Etika</h2>
                    <p class="text-lg t-muted max-w-2xl mx-auto">
                        Dibangun dengan prinsip keamanan dan privasi sejak awal
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="card p-6 fade-in">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Multi-Tenant Isolation</h3>
                        <p class="text-sm t-muted">Row-Level Security (RLS) di PostgreSQL memastikan data tenant terisolasi penuh</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.1s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Server-Side Scoring</h3>
                        <p class="text-sm t-muted">Semua scoring dan validasi dilakukan server-side untuk mencegah manipulasi</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.2s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Role-Based Access</h3>
                        <p class="text-sm t-muted">Kontrol akses berbasis role: SuperAdmin, TenantAdmin, dan User dengan policy ketat</p>
                    </div>

                    <div class="card p-6 fade-in" style="animation-delay: 0.3s">
                        <div class="w-10 h-10 rounded-lg mb-4 flex items-center justify-center" style="background: var(--brand-soft)">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" style="color: var(--brand)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="font-display font-bold text-lg t-ink mb-2">Privasi & Etika</h3>
                        <p class="text-sm t-muted">Tidak menyimpan data sensitif simulasi; awareness diukur dari partisipasi, bukan penipuan</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="py-20 px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="font-display text-4xl font-bold t-ink mb-4">
                    Siap meningkatkan awareness keamanan organisasi Anda?
                </h2>
                <p class="text-lg t-muted mb-8">
                    Mulai bangun budaya keamanan siber yang terukur dengan platform kami
                </p>
                <Link :href="ctaHref" class="btn btn-primary">
                    {{ ctaText }}
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-8 px-6 lg:px-8 b-line border-t">
            <div class="max-w-7xl mx-auto text-center">
                <p class="text-sm t-muted">
                    Security Awareness Platform — Bangun budaya keamanan siber yang terukur
                </p>
            </div>
        </footer>
    </div>
</template>
