<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { BaseAlert, BaseBadge, BaseButton, BaseTableContainer } from '@/Components';

defineProps({ modules: { type: Array, default: () => [] } });

const errors = computed(() => usePage().props.errors ?? {});
const mutationError = computed(() => errors.value.action ?? errors.value.pretest_quiz_id ?? errors.value.posttest_quiz_id ?? null);

const statusFilter = ref('all');
const processing = ref(null);

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
    if (processing.value) return;
    processing.value = `publish:${id}`;
    router.post(route('platform.modules.publish', id), {}, {
        onFinish: () => processing.value = null,
    });
};

const archive = (id) => {
    if (confirm('Arsipkan modul ini? Modul tidak akan terlihat di tenant.')) {
        if (processing.value) return;
        processing.value = `archive:${id}`;
        router.post(route('platform.modules.archive', id), {}, {
            onFinish: () => processing.value = null,
        });
    }
};

const destroy = (id) => {
    if (confirm('Hapus permanen modul ini?')) {
        if (processing.value) return;
        processing.value = `destroy:${id}`;
        router.delete(route('platform.modules.destroy', id), {
            onFinish: () => processing.value = null,
        });
    }
};

const statusVariant = (status) => {
    const map = {
        draft: 'neutral',
        published: 'success',
        archived: 'warning',
    };
    return map[status] || 'neutral';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Terbit', archived: 'Diarsipkan' };
    return map[status] || status;
};
</script>

<template>
    <Head title="Studio Konten" />

    <AppLayout title="Studio Konten">
        <BaseAlert v-if="mutationError" variant="danger" class="mb-4">{{ mutationError }}</BaseAlert>
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm t-muted">
                Kelola materi pembelajaran dari draft hingga siap digunakan organisasi.
            </p>
            <Link :href="route('platform.modules.create')" class="btn btn-primary min-h-[44px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2">
                Buat modul
            </Link>
        </div>

        <!-- Filter chips -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="(count, key) in statusCounts"
                :key="key"
                @click="statusFilter = key"
                class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-medium transition-all active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
                :class="statusFilter === key ? 'chip-active shadow-md' : 'bg-surface t-ink hover:bg-app border b-line'"
            >
                {{ key === 'all' ? 'Semua modul' : statusLabel(key) }} <span class="opacity-75">({{ count }})</span>
            </button>
        </div>

        <BaseTableContainer>
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
                        <td colspan="5"><EmptyState message="Belum ada modul dengan status ini." /></td>
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
                            <BaseBadge :variant="statusVariant(module.status)">
                                {{ statusLabel(module.status) }}
                            </BaseBadge>
                        </td>
                        <td class="px-6 py-3 text-right t-muted">{{ module.assignments_count }}</td>
                        <td class="px-6 py-3 text-right space-x-3">
                            <Link :href="route('platform.modules.edit', module.id)" class="text-sm font-medium hover:underline focus-visible:outline-none focus-visible:ring-2" style="color: var(--brand)">Edit</Link>
                            <BaseButton v-if="module.status === 'draft'" size="sm" variant="secondary" :loading="processing === `publish:${module.id}`" :disabled="processing !== null && processing !== `publish:${module.id}`" @click="publish(module.id)">{{ processing === `publish:${module.id}` ? 'Memproses...' : 'Terbitkan' }}</BaseButton>
                            <BaseButton v-if="module.status === 'published'" size="sm" variant="secondary" :loading="processing === `archive:${module.id}`" :disabled="processing !== null && processing !== `archive:${module.id}`" @click="archive(module.id)">{{ processing === `archive:${module.id}` ? 'Memproses...' : 'Arsipkan' }}</BaseButton>
                            <BaseButton v-if="module.status === 'archived'" size="sm" variant="secondary" :loading="processing === `publish:${module.id}`" :disabled="processing !== null && processing !== `publish:${module.id}`" @click="publish(module.id)">{{ processing === `publish:${module.id}` ? 'Memproses...' : 'Terbitkan lagi' }}</BaseButton>
                            <BaseButton size="sm" variant="danger" :loading="processing === `destroy:${module.id}`" :disabled="processing !== null && processing !== `destroy:${module.id}`" @click="destroy(module.id)">{{ processing === `destroy:${module.id}` ? 'Memproses...' : 'Hapus' }}</BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </BaseTableContainer>
    </AppLayout>
</template>
