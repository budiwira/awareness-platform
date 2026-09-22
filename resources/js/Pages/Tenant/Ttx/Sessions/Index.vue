<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
});

const statusMeta = {
    draft: { label: 'Draft', class: 'badge chip-brand' },
    ready: { label: 'Siap', class: 'badge badge-ok' },
    in_progress: { label: 'Berlangsung', class: 'badge badge-warn' },
    debrief: { label: 'Debrief', class: 'badge' },
    completed: { label: 'Selesai', class: 'badge' },
};

const statusOf = (status) => statusMeta[status] ?? { label: status, class: 'badge' };

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const isEmpty = computed(() => props.sessions.length === 0);
</script>

<template>
    <Head title="Sesi Tabletop" />

    <AppLayout title="Sesi Tabletop Exercise">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.exercises.index')" class="text-sm hover:underline" style="color: var(--brand);">
                ← Kembali ke Daftar Exercise
            </Link>
        </div>

        <div class="fade-in">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm t-muted">
                    Pantau seluruh sesi tabletop organisasi Anda dan siapkan sesi sebelum dijalankan.
                </p>
                <Link :href="route('tenant.ttx.exercises.index')" class="btn btn-primary">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Buat Session
                </Link>
            </div>

            <div v-if="isEmpty" class="card">
                <EmptyState
                    title="Belum ada sesi"
                    message="Buat sesi baru dari menu Latihan untuk mulai menyiapkan tabletop exercise."
                />
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="session in sessions"
                    :key="session.id"
                    class="card flex flex-col gap-4 p-5 transition hover:shadow-md"
                >
                    <header class="flex items-start justify-between gap-3">
                        <h2 class="font-display text-base font-semibold t-ink">{{ session.title }}</h2>
                        <span :class="statusOf(session.status).class">{{ statusOf(session.status).label }}</span>
                    </header>

                    <dl class="space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="t-muted">Jadwal</dt>
                            <dd class="t-ink">{{ formatDate(session.scheduled_at) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="t-muted">Fasilitator</dt>
                            <dd class="t-ink">{{ session.facilitator_name ?? 'Belum ditetapkan' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="t-muted">Peserta</dt>
                            <dd class="t-ink tabular-nums">{{ session.participant_count }}</dd>
                        </div>
                    </dl>

                    <footer class="mt-auto">
                        <Link
                            :href="route('tenant.ttx.sessions.prepare', session.id)"
                            class="btn btn-secondary w-full"
                        >
                            Siapkan Sesi
                            <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </footer>
                </article>
            </div>
        </div>
    </AppLayout>
</template>