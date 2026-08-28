<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const unread = computed(() => page.props.unread ?? 0);

const menus = computed(() => {
    const role = user.value?.role;

    if (role === 'super_admin') {
        return [
            { label: 'Platform Dashboard', route: 'platform.dashboard', pattern: 'platform.*', soon: false },
            { label: 'Tenants', route: 'platform.tenants.index', pattern: 'platform.tenants.*', soon: false },
            { label: 'Content Library', route: 'platform.modules.index', pattern: 'platform.modules.*', soon: false },
            { label: 'Quizzes', route: 'platform.quizzes.index', pattern: 'platform.quizzes.*', soon: false },
            { label: 'Case Studies', route: 'platform.cases.index', pattern: 'platform.cases.*', soon: false },
            { label: 'CTF', route: 'platform.ctf.index', pattern: 'platform.ctf.*', soon: false },
            { label: 'Reports', route: 'platform.reports', pattern: 'platform.reports', soon: false },
            { label: 'Plans & Billing', route: null, pattern: null, soon: true },
            { label: 'Audit Log', route: null, pattern: null, soon: true },
        ];
    }

    if (role === 'tenant_admin') {
        return [
            { label: 'Tenant Dashboard', route: 'tenant.dashboard', pattern: 'tenant.dashboard', soon: false },
            { label: 'Users', route: 'tenant.users.index', pattern: 'tenant.users.*', soon: false },
            { label: 'Training', route: 'tenant.assignments.index', pattern: 'tenant.assignments.*', soon: false },
            { label: 'Tabletop (TTX)', route: 'tenant.ttx.index', pattern: 'tenant.ttx.index', soon: false },
            { label: 'Simulasi TTX', route: 'tenant.ttx.exercises.index', pattern: 'tenant.ttx.exercises.*', soon: false },
            { label: 'Reports', route: 'tenant.reports', pattern: 'tenant.reports', soon: false },
            { label: 'Policy', route: null, pattern: null, soon: true },
        ];
    }

    return [
        { label: 'My Dashboard', route: 'user.dashboard', pattern: 'user.dashboard', soon: false },
        { label: 'Training', route: 'user.training.index', pattern: 'user.training.*', soon: false },
        { label: 'Case Studies', route: 'user.cases.index', pattern: 'user.cases.*', soon: false },
        { label: 'CTF', route: 'user.ctf.index', pattern: 'user.ctf.*', soon: false },
        { label: 'Quiz', route: null, pattern: null, soon: true },
        { label: 'My Score', route: 'user.score', pattern: 'user.score', soon: false },
    ];
});

const isActive = (item) => (item.pattern ? route().current(item.pattern) : false);

const roleBadgeClass = computed(() => ({
    super_admin: 'bg-purple-100 text-purple-700',
    tenant_admin: 'bg-indigo-100 text-indigo-700',
    user: 'bg-emerald-100 text-emerald-700',
}[user.value?.role] ?? 'bg-gray-100 text-gray-700'));

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <aside class="fixed inset-y-0 left-0 w-64 bg-slate-900 text-white flex flex-col">
            <div class="flex items-center gap-3 px-6 h-16 border-b border-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l8 4v6c0 5.25-3.4 9.74-8 10-4.6-.26-8-4.75-8-10V6l8-4z" />
                </svg>
                <div class="min-w-0">
                    <div class="font-bold leading-tight truncate">Awareness Platform</div>
                    <div class="text-xs text-slate-400 truncate">{{ user?.tenant_name ?? 'Platform Control' }}</div>
                </div>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <template v-for="item in menus" :key="item.label">
                    <Link
                        v-if="!item.soon"
                        :href="route(item.route)"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium"
                        :class="isActive(item) ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
                    >
                        {{ item.label }}
                    </Link>
                    <div
                        v-else
                        class="flex items-center justify-between px-3 py-2 rounded-lg text-sm text-slate-500 cursor-not-allowed"
                    >
                        {{ item.label }}
                        <span class="text-[10px] uppercase tracking-wide bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full">
                            soon
                        </span>
                    </div>
                </template>
            </nav>

            <div class="px-4 py-4 border-t border-slate-800">
                <div class="text-sm font-medium truncate">{{ user?.name }}</div>
                <div class="text-xs text-slate-400 mb-3">{{ user?.role_label }}</div>
                <button
                    @click="logout"
                    class="w-full px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm transition"
                >
                    Logout
                </button>
            </div>
        </aside>

        <div class="pl-64">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8">
                <h1 class="text-lg font-semibold text-gray-800">{{ title }}</h1>
                <div class="flex items-center gap-4">
                    <Link :href="route('notifications.index')" class="relative text-gray-500 hover:text-gray-700" title="Notifikasi">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span v-if="unread > 0"
                            class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5">
                            {{ unread }}
                        </span>
                    </Link>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="roleBadgeClass">
                        {{ user?.role_label }}
                    </span>
                </div>
            </header>

            <main class="p-8">
                <slot />
            </main>
        </div>
    </div>
</template>