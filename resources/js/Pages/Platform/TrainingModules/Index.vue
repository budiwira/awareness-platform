<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ modules: Array });

const errors = computed(() => usePage().props.errors ?? {});

const statusFilter = ref('all');

const filteredModules = computed(() => {
    const data = usePage().props.modules;
    if (statusFilter.value === 'all') return data;
    return data.filter(m => m.status === statusFilter.value);
});

const statusCounts = computed(() => {
    const data = usePage().props.modules;
    return {
        all: data.length,
        draft: data.filter(m => m.status === 'draft').length,
        published: data.filter(m => m.status === 'published').length,
        archived: data.filter(m => m.status === 'archived').length,
    };
});

const publish = (id) => {
    router.post(route('platform.modules.publish', id));
};

const archive = (id) => {
    if (confirm('Arsipkan modul ini? Modul tidak akan terlihat di tenant.')) {
        router.post(route('platform.modules.archive', id));
    }
};

const destroy = (id) => {
    if (confirm('Hapus permanen modul ini?')) {
        router.delete(route('platform.modules.destroy', id));
    }
};

const statusBadge = (status) => {
    const map = {
        draft: 'bg-surface2 t-ink',
        published: 'badge-ok',
        archived: 'badge-warn',
    };
    return map[status] || 'bg-surface2 t-ink';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Published', archived: 'Archived' };
    return map[status] || status;
};
</script>

<template>
    <Head title="Studio Konten" />

    <AppLayout title="Studio Konten">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Pipeline authoring konten — Draft, Published, Archived.
            </p>
            <Link :href="route('platform.modules.create')" class="btn btn-primary">
                + Buat Modul
            </Link>
        </div>

        <!-- Filter chips -->
        <div class="flex gap-2 mb-6">
            <button
                v-for="(count, key) in statusCounts"
                :key="key"
                @click="statusFilter = key"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                :class="statusFilter === key ? 'chip-active shadow-md' : 'bg-surface t-ink hover:bg-app border b-line'"
            >
                {{ key === 'all' ? 'Semua' : statusLabel(key) }} <span class="opacity-75">({{ count }})</span>
            </button>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Judul</th>
                        <th class="px-6 py-3 font-medium">Durasi</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Ditugaskan</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filteredModules.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center t-muted">
                            <div class="flex flex-col items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div class="text-sm">Tidak ada modul dengan status ini.</div>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="module in filteredModules" :key="module.id" class="border-b b-line hover:bg-app/50 transition-colors">
                        <td class="px-6 py-3">
                            <Link :href="route('platform.modules.show', module.id)" class="font-medium t-ink hover:chip-brand transition-colors">
                                {{ module.title }}
                            </Link>
                            <div class="text-xs t-muted mt-0.5">{{ module.description?.substring(0, 60) }}{{ module.description?.length > 60 ? '...' : '' }}</div>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ module.duration_minutes }} menit</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(module.status)">
                                {{ statusLabel(module.status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right t-muted">{{ module.assignments_count }}</td>
                        <td class="px-6 py-3 text-right space-x-3">
                            <Link :href="route('platform.modules.edit', module.id)" class="text-indigo-600 text-sm font-medium hover:underline">Edit</Link>
                            <button v-if="module.status === 'draft'" @click="publish(module.id)" class="badge-ok text-sm font-medium hover:underline">Publish</button>
                            <button v-if="module.status === 'published'" @click="archive(module.id)" class="badge-warn text-sm font-medium hover:underline">Archive</button>
                            <button @click="destroy(module.id)" class="text-red-600 text-sm font-medium hover:underline">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
