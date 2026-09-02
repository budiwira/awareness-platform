<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

defineProps({ title: { type: String, default: 'Dashboard' } });

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unread = computed(() => page.props.unread ?? 0);
const showMobileNav = ref(false);

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

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .map((s) => s[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
);

const menus = computed(() => {
    const role = user.value?.role;
    const entitlements = page.props.entitlements;

    if (role === 'super_admin') {
        return [
            { section: 'Platform', items: [
                { label: 'Dashboard', route: 'platform.dashboard' },
                { label: 'Tenants', route: 'platform.tenants.index' },
                { label: 'Reports', route: 'platform.reports' },
                { label: 'packages & Billing', route: 'platform.packages.index' },
                { label: 'Billing Requests', route: 'platform.billing.requests' },
                { label: 'Users', route: 'platform.users.index' },
            ]},
            { section: 'Konten', items: [
                { label: 'Studio Konten', route: 'platform.modules.index' },
                { label: 'Quizzes', route: 'platform.quizzes.index' },
                { label: 'Case Studies', route: 'platform.cases.index' },
                { label: 'CTF', route: 'platform.ctf.index' },
            ]},
        ];
    }

    if (role === 'tenant_admin') {
        return [
            { section: 'Organisasi', items: [
                { label: 'Dashboard', route: 'tenant.dashboard' },
                { label: 'Users', route: 'tenant.users.index' },
                { label: 'Billing', route: 'tenant.billing.index' },
            ]},
            { section: 'Training', items: [
                { label: 'Penugasan', route: 'tenant.assignments.index' },
                { label: 'Reports', route: 'tenant.reports' },
            ]},
            { section: 'Simulasi', items: [
                { label: 'Tabletop (TTX)', route: 'tenant.ttx.index', locked: !entitlements?.features?.includes('ttx') },
                { label: 'Simulasi TTX', route: 'tenant.ttx.exercises.index', locked: !entitlements?.features?.includes('ttx') },
                { label: 'Simulasi Phishing', route: 'tenant.phishing.index', locked: !entitlements?.features?.includes('phishing') },
            ]},
        ];
    }

    return [
        { section: 'Saya', items: [
            { label: 'Dashboard', route: 'user.dashboard' },
            { label: 'My Score', route: 'user.score' },
            { label: 'Badge Saya', route: 'user.badges.index' },
            { label: 'Leaderboard', route: 'user.leaderboard.index' },
        ]},
        { section: 'Belajar', items: [
            { label: 'Training', route: 'user.training.index' },
            { label: 'Case Studies', route: 'user.cases.index', locked: !entitlements?.features?.includes('case_studies') },
            { label: 'CTF', route: 'user.ctf.index', locked: !entitlements?.features?.includes('ctf') },
        ]},
    ];
});

const isActive = (item) => {
    if (!item.route) return false;
    const path = route(item.route).replace(/^https?:\/\/[^/]+/, '');
    const matches = page.url === path || page.url.startsWith(path + '/');
    if (!matches) return false;
    
    // Longest-prefix wins: hitung semua item yang cocok, aktifkan yang path-nya terpanjang
    const allMatches = menus.value.flatMap(g => g.items).filter(it => {
        if (!it.route) return false;
        const p = route(it.route).replace(/^https?:\/\/[^/]+/, '');
        return page.url === p || page.url.startsWith(p + '/');
    });
    
    if (allMatches.length === 0) return false;
    const longest = allMatches.reduce((max, it) => {
        const p = route(it.route).replace(/^https?:\/\/[^/]+/, '');
        return p.length > route(max.route).replace(/^https?:\/\/[^/]+/, '').length ? it : max;
    });
    
    return item.route === longest.route;
};

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="min-h-screen flex bg-app">
        <!-- Sidebar desktop -->
        <aside class="hidden lg:flex lg:flex-col w-64 shrink-0" style="background: var(--sidebar)">
            <div class="flex items-center gap-3 px-6 h-16 border-b b-line">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-display font-bold" style="background: rgba(124,58,237,.2); color: var(--brand-strong)">SA</div>
                <div>
                    <div class="font-display font-semibold leading-tight t-ink">Awareness</div>
                    <div class="text-[11px] t-muted">Security Platform</div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                <div v-for="group in menus" :key="group.section">
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider t-muted">
                        {{ group.section }}
                    </div>
                    <div class="space-y-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.route"
                            :href="route(item.route)"
                            class="relative flex items-center gap-2 px-3 py-2 rounded-full text-sm transition-all"
                            :class="isActive(item) ? 'font-medium' : ''"
                            :style="isActive(item) ? 'background: rgba(124,58,237,.15); color: var(--ink); box-shadow: var(--glow)' : 'color: var(--muted)'"
                            @mouseenter="!isActive(item) && ($event.currentTarget.style.background = 'var(--surface)', $event.currentTarget.style.color = 'var(--ink)')"
                            @mouseleave="!isActive(item) && ($event.currentTarget.style.background = '', $event.currentTarget.style.color = '')"
                        >
                            <span class="flex-1">{{ item.label }}</span>
                            <svg v-if="item.locked" class="w-4 h-4" style="color: var(--warn)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="px-6 py-4 border-t b-line text-[11px] t-muted">
                {{ user?.tenant_name ?? 'Platform Operations' }}
            </div>
        </aside>

        <!-- Mobile slide-over -->
        <div v-if="showMobileNav" class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/50" @click="showMobileNav = false"></div>
            <aside class="absolute inset-y-0 left-0 w-72 flex flex-col" style="background: var(--sidebar)">
                <div class="flex items-center justify-between px-6 h-16 border-b b-line">
                    <div class="font-display font-semibold t-ink">Awareness</div>
                    <button class="t-muted" @click="showMobileNav = false">âœ•</button>
                </div>
                <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                    <div v-for="group in menus" :key="group.section">
                        <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider t-muted">
                            {{ group.section }}
                        </div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in group.items"
                                :key="item.route"
                                :href="route(item.route)"
                                class="flex items-center gap-2 px-3 py-2 rounded-full text-sm"
                                :class="isActive(item) ? 'font-medium' : ''"
                                :style="isActive(item) ? 'background: rgba(124,58,237,.15); color: var(--ink)' : 'color: var(--muted)'"
                                @click="showMobileNav = false"
                            >
                                <span class="flex-1">{{ item.label }}</span>
                                <svg v-if="item.locked" class="w-4 h-4" style="color: var(--warn)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </nav>
            </aside>
        </div>

        <!-- Konten -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 flex items-center justify-between px-6 sticky top-0 z-30 backdrop-blur relative" style="background: var(--header)">
                <div class="flex items-center gap-3">
                    <button class="lg:hidden t-muted" @click="showMobileNav = true">â˜°</button>
                    <h1 class="font-display text-lg font-bold t-ink">{{ title }}</h1>
                </div>

                <div class="flex items-center gap-4">
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

                    <Link :href="route('notifications.index')" class="relative t-muted transition-colors" @mouseenter="$event.currentTarget.style.color = 'var(--ink)'" @mouseleave="$event.currentTarget.style.color = ''">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span
                            v-if="unread > 0"
                            class="absolute -top-1.5 -right-1.5 text-[10px] font-bold rounded-full px-1.5 py-0.5"
                            style="background: var(--warn); color: var(--bg)"
                        >{{ unread }}</span>
                    </Link>

                    <span v-if="user?.tenant_name" class="badge chip-brand hidden sm:inline-flex">
                        {{ user.tenant_name }}
                    </span>

                    <div class="flex items-center gap-3">
                        <div 
                            v-if="user?.avatar_path"
                            class="w-8 h-8 rounded-full ring-2 overflow-hidden bg-cover bg-center"
                            :style="{ backgroundImage: `url(/storage/${user.avatar_path})`, ringColor: 'var(--brand)' }"
                        ></div>
                        <div 
                            v-else
                            class="w-8 h-8 rounded-full text-xs font-bold flex items-center justify-center ring-2" 
                            style="background: var(--brand); color: var(--white); ring-color: var(--brand)"
                        >
                            {{ initials }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <div class="text-sm font-medium t-ink">{{ user?.name }}</div>
                            <div class="text-[11px] t-muted">{{ user?.role_label }}</div>
                        </div>
                    </div>

                    <button class="transition-colors t-muted" title="Keluar" @click="logout" @mouseenter="$event.currentTarget.style.color = 'var(--danger)'" @mouseleave="$event.currentTarget.style.color = ''">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>

                <div class="beam absolute bottom-0 left-0 right-0"></div>
            </header>

            <main class="flex-1 p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <Toast />
    </div>
</template>