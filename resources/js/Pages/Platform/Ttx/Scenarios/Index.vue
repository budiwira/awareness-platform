<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ scenarios: { type: Array, default: () => [] } });
const page = usePage();
const busyId = ref(null);
const loading = ref(false);
const loadError = ref('');
const errors = computed(() => page.props.errors ?? {});
const statusLabel = { draft: 'Draft', published: 'Terbit', archived: 'Arsip' };
const statusClass = { draft: 'badge-warn', published: 'badge-ok', archived: 'badge-danger' };
const formattedDate = (value) => value ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : '—';

function visit(url) {
    router.visit(url, {
        onStart: () => { loading.value = true; loadError.value = ''; },
        onFinish: () => { loading.value = false; },
        onError: () => { loadError.value = 'Halaman tidak dapat dimuat. Coba lagi.'; },
    });
}

function changeStatus(item, action) {
    busyId.value = item.id;
    router.post(route(`platform.ttx.scenarios.${action}`, item.id), {}, {
        preserveScroll: true,
        onFinish: () => { busyId.value = null; },
    });
}
</script>

<template>
    <Head title="Katalog Skenario Tabletop" />
    <AppLayout title="Katalog Skenario Tabletop">
        <main class="fade-in space-y-6">
            <header class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: var(--brand)">Katalog Platform</p>
                    <h1 class="font-display mt-2 text-2xl t-ink">Skenario Tabletop</h1>
                    <p class="mt-2 max-w-2xl text-sm t-muted">Tulis dan terbitkan template yang dapat disalin oleh admin tenant ke Exercise mereka.</p>
                </div>
                <Link :href="route('platform.ttx.scenarios.create')" class="btn btn-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 active:translate-y-px">Buat Skenario</Link>
            </header>

            <p v-if="loadError || Object.keys(errors).length" role="alert" class="card p-4 text-sm badge-danger">{{ loadError || Object.values(errors)[0] }}</p>
            <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Memuat skenario">
                <div v-for="n in 3" :key="n" class="card skeleton h-44" />
            </div>
            <div v-else-if="props.scenarios.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in props.scenarios" :key="item.id" class="card flex flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-display text-lg t-ink">{{ item.title }}</h2>
                        <span class="badge" :class="statusClass[item.status]">{{ statusLabel[item.status] }}</span>
                    </div>
                    <p class="text-sm t-muted">{{ (item.capability_codes ?? []).join(', ') || 'Belum ada capability' }}</p>
                    <div class="mt-auto flex items-center justify-between text-sm t-muted">
                        <span>{{ item.active_inject_count }} inject aktif</span>
                        <time :datetime="item.updated_at">{{ formattedDate(item.updated_at) }}</time>
                    </div>
                    <div class="flex flex-wrap gap-2 border-t b-line pt-4">
                        <button type="button" class="btn btn-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" @click="visit(route('platform.ttx.scenarios.edit', item.id))">{{ item.status === 'archived' ? 'Lihat' : 'Edit' }}</button>
                        <button v-if="item.status === 'draft'" type="button" class="btn btn-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="busyId === item.id" @click="changeStatus(item, 'publish')">{{ busyId === item.id ? 'Memproses...' : 'Terbitkan' }}</button>
                        <button v-if="item.status !== 'archived'" type="button" class="btn btn-ghost focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="busyId === item.id" @click="changeStatus(item, 'archive')">{{ busyId === item.id ? 'Memproses...' : 'Arsipkan' }}</button>
                    </div>
                </article>
            </div>
            <div v-else class="card"><EmptyState title="Belum ada skenario" message="Buat draft skenario pertama untuk memulai katalog Tabletop." /></div>
        </main>
    </AppLayout>
</template>
