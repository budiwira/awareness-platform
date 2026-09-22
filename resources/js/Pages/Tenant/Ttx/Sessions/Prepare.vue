<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    session: { type: Object, required: true },
    exercise_title: { type: String, default: null },
    inject_count: { type: Number, default: 0 },
    facilitator: { type: Object, default: null },
    facilitator_count: { type: Number, default: 0 },
    participants: { type: Array, default: () => [] },
    readiness: { type: Object, default: () => ({}) },
    can_open_console: { type: Boolean, default: false },
});

const statusMeta = {
    draft: { label: 'Draft', class: 'badge chip-brand' },
    ready: { label: 'Siap', class: 'badge badge-ok' },
    in_progress: { label: 'Berlangsung', class: 'badge badge-warn' },
    debrief: { label: 'Debrief', class: 'badge' },
    completed: { label: 'Selesai', class: 'badge' },
};

const status = computed(() => statusMeta[props.session.status] ?? { label: props.session.status, class: 'badge' });

const roleLabels = {
    facilitator: 'Fasilitator',
    security: 'Keamanan',
    it_operations: 'IT Operations',
    people_hr: 'People & HR',
    communications: 'Komunikasi',
    management: 'Manajemen',
};

const roleLabel = (role) => roleLabels[role] ?? role;

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
};

const checklist = computed(() => [
    { key: 'has_exercise_snapshot', label: 'Snapshot exercise tersimpan' },
    { key: 'has_inject', label: 'Minimal satu inject tersedia' },
    { key: 'exactly_one_facilitator', label: 'Tepat satu fasilitator' },
    { key: 'has_participants', label: 'Peserta sudah ditetapkan' },
    { key: 'all_injects_pending', label: 'Semua inject berstatus pending' },
    { key: 'not_started', label: 'Sesi belum dimulai' },
]);
</script>

<template>
    <Head :title="`Persiapan — ${session.title}`" />

    <AppLayout :title="`Persiapan: ${session.title}`">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.sessions.index')" class="text-sm hover:underline" style="color: var(--brand);">
                ← Kembali ke Daftar Sesi
            </Link>
        </div>

        <div class="fade-in space-y-6">
            <!-- Ringkasan sesi -->
            <section class="card overflow-hidden">
                <div class="p-6 sm:p-8" style="background: linear-gradient(135deg, var(--brand) 0%, var(--brand-strong) 100%);">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="font-display text-2xl font-bold text-white">{{ session.title }}</h1>
                            <p class="mt-1 text-sm text-white/80">
                                {{ exercise_title ?? 'Exercise terkait' }}
                            </p>
                        </div>
                        <span :class="status.class">{{ status.label }}</span>
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-px sm:grid-cols-3" style="background: var(--line);">
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Jadwal</dt>
                        <dd class="mt-1 text-sm t-ink">{{ formatDate(session.scheduled_at) }}</dd>
                    </div>
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Jumlah Inject</dt>
                        <dd class="mt-1 text-sm tabular-nums t-ink">{{ inject_count }}</dd>
                    </div>
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Fasilitator</dt>
                        <dd class="mt-1 text-sm t-ink">{{ facilitator?.name ?? 'Belum ditetapkan' }}</dd>
                    </div>
                </dl>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Peserta -->
                <section class="card p-6 lg:col-span-2">
                    <h2 class="font-display text-lg font-bold t-ink">Roster Peserta</h2>
                    <p class="mb-4 mt-1 text-sm t-muted">
                        Daftar peserta yang sudah ditetapkan pada sesi ini.
                    </p>

                    <div v-if="participants.length === 0" class="rounded-xl border border-dashed p-6 text-center text-sm t-muted" style="border-color: var(--line);">
                        Belum ada peserta yang ditetapkan.
                    </div>

                    <ul v-else class="divide-y" style="border-color: var(--line);">
                        <li
                            v-for="participant in participants"
                            :key="participant.id"
                            class="flex items-center justify-between gap-4 py-3"
                        >
                            <span class="min-w-0 truncate text-sm t-ink">{{ participant.name }}</span>
                            <span class="badge chip-brand shrink-0">{{ roleLabel(participant.role) }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Checklist kesiapan (server-derived) -->
                <section class="card p-6">
                    <h2 class="font-display text-lg font-bold t-ink">Checklist Kesiapan</h2>
                    <p class="mb-4 mt-1 text-sm t-muted">Dihitung oleh server berdasarkan kontrak readiness.</p>

                    <ul class="space-y-3">
                        <li v-for="item in checklist" :key="item.key" class="flex items-start gap-3 text-sm">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                                :style="readiness[item.key] ? 'background: var(--ok-bg); color: var(--ok);' : 'background: var(--surface-2); color: var(--muted);'"
                                aria-hidden="true"
                            >
                                <svg v-if="readiness[item.key]" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </span>
                            <span :class="readiness[item.key] ? 't-ink' : 't-muted'">{{ item.label }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Aksi -->
            <div class="flex justify-end">
                <Link
                    v-if="can_open_console"
                    :href="route('tenant.ttx.sessions.console', session.id)"
                    class="btn btn-primary"
                >
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Buka Console Fasilitator
                </Link>
            </div>
        </div>
    </AppLayout>
</template>