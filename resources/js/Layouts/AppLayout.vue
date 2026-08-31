<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/Toast.vue';

defineProps({ title: { type: String, default: 'Dashboard' } });

const page = usePage();
const user = computed(() => page.props.auth?.user);
const unread = computed(() => page.props.unread ?? 0);
const showMobileNav = ref(false);

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

    if (role === 'super_admin') {
        return [
            { section: 'Platform', items: [
                { label: 'Dashboard', route: 'platform.dashboard' },
                { label: 'Tenants', route: 'platform.tenants.index' },
                { label: 'Reports', route: 'platform.reports' },
                { label: 'Plans & Billing', route: 'platform.plans.index' },
            ]},
            { section: 'Konten', items: [
                { label: 'Content Library', route: 'platform.modules.index' },
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
                { label: 'Tabletop (TTX)', route: 'tenant.ttx.index' },
                { label: 'Simulasi TTX', route: 'tenant.ttx.exercises.index' },
            ]},
        ];
    }

    return [
        { section: 'Saya', items: [
            { label: 'Dashboard', route: 'user.dashboard' },
            { label: 'My Score', route: 'user.score' },
        ]},
        { section: 'Belajar', items: [
            { label: 'Training', route: 'user.training.index' },
            { label: 'Case Studies', route: 'user.cases.index' },
            { label: 'CTF', route: 'user.ctf.index' },
        ]},
    ];
});

const isActive = (item) => {
    if (!item.route) return false;
    const path = route(item.route).replace(/^https?:\/\/[^/]+/, '');
    return page.url === path || page.url.startsWith(path + '/');
};

const logout = () => router.post(route('logout'));
</script>

<template>
    <div class="min-h-screen flex bg-gray-50">
        <!-- Sidebar desktop -->
        <aside
            class="hidden lg:flex lg:flex-col w-64 shrink-0 text-teal-100"
            style="background: linear-gradient(180deg, #0c3b38 0%, #0a2f2d 100%)"
        >
            <div class="flex items-center gap-3 px-6 h-16 border-b border-white/10">
                <div class="w-9 h-9 rounded-xl bg-teal-500/20 text-teal-300 flex items-center justify-center font-display font-bold">SA</div>
                <div>
                    <div class="font-display font-semibold text-white leading-tight">Awareness</div>
                    <div class="text-[11px] text-teal-300/70">Security Platform</div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                <div v-for="group in menus" :key="group.section">
                    <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-teal-300/60">
                        {{ group.section }}
                    </div>
                    <div class="space-y-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.route"
                            :href="route(item.route)"
                            class="relative flex items-center px-3 py-2 rounded-lg text-sm transition-colors"
                            :class="isActive(item) ? 'bg-white/10 text-white font-medium' : 'hover:bg-white/5 hover:text-white'"
                        >
                            <span
                                v-if="isActive(item)"
                                class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 rounded-r bg-amber-400"
                            ></span>
                            {{ item.label }}
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="px-6 py-4 border-t border-white/10 text-[11px] text-teal-300/60">
                {{ user?.tenant_name ?? 'Platform Operations' }}
            </div>
        </aside>

        <!-- Mobile slide-over -->
        <div v-if="showMobileNav" class="fixed inset-0 z-40 lg:hidden">
            <div class="absolute inset-0 bg-black/50" @click="showMobileNav = false"></div>
            <aside
                class="absolute inset-y-0 left-0 w-72 flex flex-col text-teal-100"
                style="background: linear-gradient(180deg, #0c3b38 0%, #0a2f2d 100%)"
            >
                <div class="flex items-center justify-between px-6 h-16 border-b border-white/10">
                    <div class="font-display font-semibold text-white">Awareness</div>
                    <button class="text-teal-200" @click="showMobileNav = false">✕</button>
                </div>
                <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                    <div v-for="group in menus" :key="group.section">
                        <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-teal-300/60">
                            {{ group.section }}
                        </div>
                        <div class="space-y-1">
                            <Link
                                v-for="item in group.items"
                                :key="item.route"
                                :href="route(item.route)"
                                class="flex items-center px-3 py-2 rounded-lg text-sm"
                                :class="isActive(item) ? 'bg-white/10 text-white font-medium' : 'hover:bg-white/5'"
                                @click="showMobileNav = false"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </nav>
            </aside>
        </div>

        <!-- Konten -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 bg-white/80 backdrop-blur border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <button class="lg:hidden text-gray-500" @click="showMobileNav = true">☰</button>
                    <h1 class="font-display text-lg font-bold text-gray-900">{{ title }}</h1>
                </div>

                <div class="flex items-center gap-4">
                    <Link :href="route('notifications.index')" class="relative text-gray-500 hover:text-gray-800 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span
                            v-if="unread > 0"
                            class="absolute -top-1.5 -right-1.5 bg-amber-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5"
                        >{{ unread }}</span>
                    </Link>

                    <span v-if="user?.tenant_name" class="badge bg-teal-50 text-teal-700 border border-teal-200 hidden sm:inline-flex">
                        {{ user.tenant_name }}
                    </span>

                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-teal-700 text-white text-xs font-bold flex items-center justify-center">
                            {{ initials }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <div class="text-sm font-medium text-gray-900">{{ user?.name }}</div>
                            <div class="text-[11px]" style="color: var(--muted)">{{ user?.role_label }}</div>
                        </div>
                    </div>

                    <button class="text-gray-400 hover:text-rose-600 transition-colors" title="Keluar" @click="logout">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </header>

            <main class="flex-1 p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <Toast />
    </div>
</template>