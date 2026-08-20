<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: 'Dashboard' },
});

const user = computed(() => usePage().props.auth.user);

const menus = computed(() => {
    const role = user.value?.role;

    if (role === 'super_admin') {
        return [
            { label: 'Platform Dashboard', route: 'platform.dashboard', soon: false },
            { label: 'Tenants', route: null, soon: true },
            { label: 'Plans & Billing', route: null, soon: true },
            { label: 'Content Library', route: null, soon: true },
            { label: 'Audit Log', route: null, soon: true },
        ];
    }

    if (role === 'tenant_admin') {
        return [
            { label: 'Tenant Dashboard', route: 'tenant.dashboard', soon: false },
            { label: 'Users', route: null, soon: true },
            { label: 'Training', route: null, soon: true },
            { label: 'Reports', route: null, soon: true },
            { label: 'Policy', route: null, soon: true },
        ];
    }

    return [
        { label: 'My Dashboard', route: 'user.dashboard', soon: false },
        { label: 'Training', route: null, soon: true },
        { label: 'Quiz', route: null, soon: true },
        { label: 'CTF', route: null, soon: true },
        { label: 'My Score', route: null, soon: true },
    ];
});

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
        <!-- Sidebar -->
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
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white"
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

        <!-- Main area -->
        <div class="pl-64">
            <header class="h-16 bg-white shadow-sm flex items-center justify-between px-8">
                <h1 class="text-lg font-semibold text-gray-800">{{ title }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="roleBadgeClass">
                    {{ user?.role_label }}
                </span>
            </header>

            <main class="p-8">
                <slot />
            </main>
        </div>
    </div>
</template>