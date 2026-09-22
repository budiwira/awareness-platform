<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    session: { type: Object, required: true },
    exercise_title: { type: String, default: null },
    inject_count: { type: Number, default: 0 },
    facilitator: { type: Object, default: null },
    facilitator_count: { type: Number, default: 0 },
    participants: { type: Array, default: () => [] },
    readiness: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
    can_open_console: { type: Boolean, default: false },
    can_assign: Boolean,
    can_assign_facilitator: Boolean,
    assignable_users: { type: Array, default: () => [] },
    role_options: { type: Array, default: () => [] },
});

const state = ref({ ...props });
watch(() => ({ ...props }), (value) => { state.value = value; });
const saving = ref(false);
const markingReady = ref(false);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const blocked = ref(false);
const facilitatorUser = ref('');
const participantUser = ref('');
const participantRole = ref('');
const regularParticipants = computed(() => state.value.participants.filter((p) => p.role !== 'facilitator'));
const regularRoles = computed(() => state.value.role_options.filter((r) => r.value !== 'facilitator'));
const busy = computed(() => saving.value || markingReady.value || loading.value || blocked.value);

function reload() {
    if (saving.value || loading.value) return;
    loading.value = true;
    router.reload({
        onSuccess: () => { blocked.value = false; error.value = ''; },
        onFinish: () => { loading.value = false; },
    });
}

async function mutate(method, url, data = {}) {
    if (busy.value) return;
    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        const response = await axios({ method, url, data, headers: { Accept: 'application/json' } });
        state.value = response.data;
        facilitatorUser.value = '';
        participantUser.value = '';
        participantRole.value = '';
        notice.value = 'Daftar peserta berhasil diperbarui.';
    } catch (failure) {
        const code = failure.response?.status;
        const messages = {
            403: 'Akses ditolak. Hak akses atau status sesi mungkin telah berubah. Muat ulang untuk memeriksa.',
            404: 'Sesi atau peserta tidak lagi tersedia. Muat ulang daftar peserta.',
            409: 'Daftar peserta atau status sesi telah berubah. Muat ulang sebelum melanjutkan.',
            422: 'Periksa pengguna dan peran yang dipilih, lalu coba lagi.',
        };
        error.value = messages[code] ?? 'Perubahan belum dapat dikonfirmasi. Muat ulang sebelum mencoba lagi.';
        blocked.value = code !== 422;
    } finally {
        saving.value = false;
    }
}

const assign = (user, role) => mutate('post', route('tenant.ttx.sessions.participants.store', state.value.session.id), { user_id: user, role });
const remove = (participant) => mutate('delete', route('tenant.ttx.sessions.participants.destroy', [state.value.session.id, participant.assignment_id]));

async function markReady() {
    if (busy.value || !state.value.permissions.can_mark_ready) return;
    markingReady.value = true;
    error.value = '';
    notice.value = '';
    try {
        const response = await axios.post(
            route('tenant.ttx.sessions.ready', state.value.session.id),
            {},
            { headers: { Accept: 'application/json' } },
        );
        state.value = response.data;
        blocked.value = false;
        notice.value = 'Sesi siap dan telah diserahkan kepada fasilitator.';
    } catch (failure) {
        const code = failure.response?.status;
        const messages = {
            403: 'Akses ditolak. Hak akses Anda mungkin telah berubah. Muat ulang untuk memeriksa.',
            409: 'Status sesi telah berubah. Muat ulang sebelum melanjutkan.',
            422: 'Sesi belum memenuhi seluruh persyaratan kesiapan. Muat ulang untuk melihat kondisi terbaru.',
        };
        error.value = messages[code] ?? 'Sesi belum dapat ditandai siap. Muat ulang sebelum mencoba lagi.';
        blocked.value = true;
    } finally {
        markingReady.value = false;
    }
}

const statusMeta = {
    draft: { label: 'Draft', class: 'badge chip-brand' },
    ready: { label: 'Siap', class: 'badge badge-ok' },
    in_progress: { label: 'Berlangsung', class: 'badge badge-warn' },
    debrief: { label: 'Debrief', class: 'badge' },
    completed: { label: 'Selesai', class: 'badge' },
};

const status = computed(() => statusMeta[state.value.session.status] ?? { label: state.value.session.status, class: 'badge' });

const roleLabel = (role) => state.value.role_options.find((option) => option.value === role)?.label ?? role;

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
};

const checklist = computed(() => [
    { key: 'has_exercise_snapshot', label: 'Skenario latihan tersimpan' },
    { key: 'has_facilitator', label: 'Tepat satu fasilitator' },
    { key: 'has_non_facilitator_participant', label: 'Minimal satu peserta selain fasilitator' },
    { key: 'has_injects', label: 'Minimal satu inject tersedia' },
    { key: 'all_injects_pending', label: 'Semua inject berstatus pending' },
]);
</script>

<template>
    <Head :title="`Persiapan — ${state.session.title}`" />

    <AppLayout title="Persiapan Sesi">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.sessions.index')" class="back-link text-sm hover:underline" style="color: var(--ink);">
                ← Kembali ke Daftar Sesi
            </Link>
        </div>

        <div class="preparation fade-in min-w-0 space-y-6">
            <!-- Ringkasan sesi -->
            <section class="card overflow-hidden">
                <div class="p-6 sm:p-8" style="background: var(--surface-2);">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h1 class="font-display break-words text-2xl font-bold t-ink">{{ state.session.title }}</h1>
                            <p class="mt-1 text-sm t-muted">
                                {{ state.exercise_title ?? 'Latihan terkait' }}
                            </p>
                        </div>
                        <span :class="status.class" class="self-start" style="color: var(--ink)">{{ status.label }}</span>
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-px sm:grid-cols-3" style="background: var(--line);">
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Jadwal</dt>
                        <dd class="mt-1 text-sm t-ink">{{ formatDate(state.session.scheduled_at) }}</dd>
                    </div>
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Jumlah Inject</dt>
                        <dd class="mt-1 text-sm tabular-nums t-ink">{{ state.inject_count }}</dd>
                    </div>
                    <div class="p-5" style="background: var(--surface);">
                        <dt class="text-xs font-semibold uppercase tracking-wide t-muted">Fasilitator</dt>
                        <dd class="mt-1 text-sm t-ink">{{ state.facilitator?.name ?? 'Belum ditetapkan' }}</dd>
                    </div>
                </dl>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Peserta -->
                <section class="card min-w-0 p-5 sm:p-6 lg:col-span-2">
                    <h2 class="font-display text-lg font-bold t-ink">Pengelolaan Peserta</h2>
                    <p class="mb-4 mt-1 text-sm t-muted">
                        Daftar peserta yang sudah ditetapkan pada sesi ini.
                    </p>

                    <p v-if="error" role="alert" class="mb-4 rounded-xl border p-4 text-sm t-ink" style="border-color: var(--line)">{{ error }} <button type="button" class="btn btn-secondary mt-2" :disabled="saving || loading" @click="reload">Muat ulang</button></p>
                    <p v-if="notice" role="status" class="mb-4 text-sm t-ink">{{ notice }}</p>
                    <div v-if="loading" class="skeleton mb-4 h-20 rounded-xl" role="status"><span class="sr-only">Memuat daftar peserta...</span></div>
                    <div :aria-busy="saving || loading">
                        <h3 class="font-display font-semibold t-ink">Fasilitator utama</h3>
                        <div v-if="state.facilitator" class="my-4 flex flex-wrap items-center justify-between gap-3 rounded-xl p-4" style="background: var(--surface-2)">
                            <span class="min-w-0 break-words t-ink">{{ state.facilitator.name }}</span>
                            <button v-if="state.facilitator.can_remove" class="btn btn-secondary" :disabled="busy" @click="remove(state.facilitator)">{{ saving ? 'Memproses...' : 'Hapus fasilitator' }}</button>
                            <span v-else class="text-sm t-muted">Tidak dapat diubah</span>
                        </div>
                        <EmptyState v-else class="roster-empty" title="Fasilitator belum ditetapkan" message="Tetapkan satu pengguna aktif untuk memandu sesi." />
                        <form v-if="state.can_assign_facilitator && state.assignable_users.length" class="mt-4 grid min-w-0 gap-3" @submit.prevent="assign(facilitatorUser, 'facilitator')">
                            <label for="facilitator-user" class="text-sm font-medium t-ink">Pengguna fasilitator</label>
                            <select id="facilitator-user" v-model="facilitatorUser" required :disabled="busy" class="input w-full"><option disabled value="">Pilih fasilitator</option><option v-for="user in state.assignable_users" :key="user.id" :value="user.id">{{ user.name }}</option></select>
                            <button class="btn btn-primary sm:justify-self-end" :disabled="busy || !facilitatorUser">{{ saving ? 'Memproses...' : 'Tetapkan fasilitator' }}</button>
                        </form>
                        <h3 class="font-display mt-6 border-t pt-6 font-semibold t-ink" style="border-color: var(--line)">Peserta sesi</h3>
                        <EmptyState v-if="!regularParticipants.length" class="roster-empty" title="Belum ada peserta" message="Tambahkan peserta beserta perannya untuk menyiapkan sesi." />
                        <ul v-else class="mt-3" aria-label="Daftar peserta">
                            <li v-for="participant in regularParticipants" :key="participant.assignment_id" class="flex flex-wrap items-center justify-between gap-3 border-b py-4" style="border-color: var(--line)">
                                <div class="min-w-0 flex-1"><p class="break-words text-sm font-semibold t-ink">{{ participant.name }}</p><p class="mt-1 text-sm t-muted">{{ roleLabel(participant.role) }}</p></div>
                                <button v-if="participant.can_remove" class="btn btn-secondary" :aria-label="`Hapus peserta ${participant.name}`" :disabled="busy" @click="remove(participant)">{{ saving ? 'Memproses...' : 'Hapus' }}</button>
                            </li>
                        </ul>
                        <form v-if="state.can_assign && state.assignable_users.length" class="mt-5 grid min-w-0 gap-4 sm:grid-cols-2" @submit.prevent="assign(participantUser, participantRole)">
                            <div class="min-w-0"><label for="participant-user" class="mb-2 block text-sm font-medium t-ink">Pengguna</label><select id="participant-user" v-model="participantUser" required :disabled="busy" class="input w-full"><option disabled value="">Pilih peserta</option><option v-for="user in state.assignable_users" :key="user.id" :value="user.id">{{ user.name }}</option></select></div>
                            <div class="min-w-0"><label for="participant-role" class="mb-2 block text-sm font-medium t-ink">Peran sesi</label><select id="participant-role" v-model="participantRole" required :disabled="busy" class="input w-full"><option disabled value="">Pilih peran</option><option v-for="role in regularRoles" :key="role.value" :value="role.value">{{ role.label }}</option></select></div>
                            <button class="btn btn-primary sm:col-span-2 sm:justify-self-end" :disabled="busy || !participantUser || !participantRole">{{ saving ? 'Memproses...' : 'Tambahkan peserta' }}</button>
                        </form>
                        <p v-else-if="state.can_assign" class="mt-5 text-sm t-muted">Tidak ada pengguna aktif lain yang dapat ditambahkan.</p>
                        <p v-else class="mt-5 text-sm t-muted">Daftar peserta terkunci pada tahap sesi ini.</p>
                        <p v-if="state.session.status === 'ready'" class="mt-4 text-sm t-muted">Fasilitator tetap. Penghapusan peserta hanya tersedia jika kesiapan sesi tetap terpenuhi.</p>
                    </div>
                </section>

                <!-- Checklist kesiapan (server-derived) -->
                <section class="card self-start p-5 sm:p-6">
                    <h2 class="font-display text-lg font-bold t-ink">Checklist Kesiapan</h2>
                    <p class="mb-4 mt-1 text-sm t-muted">Diperbarui setelah perubahan tersimpan.</p>

                    <ul class="space-y-3">
                        <li v-for="item in checklist" :key="item.key" class="flex items-start gap-3 text-sm">
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full"
                                :style="state.readiness[item.key] ? 'background: var(--ok-bg); color: var(--ok);' : 'background: var(--surface-2); color: var(--muted);'"
                                aria-hidden="true"
                            >
                                <svg v-if="state.readiness[item.key]" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </span>
                            <span :class="state.readiness[item.key] ? 't-ink' : 't-muted'"><span class="sr-only">{{ state.readiness[item.key] ? 'Terpenuhi:' : 'Belum terpenuhi:' }}</span> {{ item.label }}</span>
                        </li>
                    </ul>

                    <div v-if="state.session.status === 'draft'" class="mt-6 border-t pt-5" style="border-color: var(--line)">
                        <p class="mb-4 text-sm t-muted">Status siap hanya dapat diberikan setelah seluruh pemeriksaan server terpenuhi.</p>
                        <button
                            type="button"
                            class="btn btn-primary w-full justify-center"
                            :disabled="busy || !state.permissions.can_mark_ready"
                            @click="markReady"
                        >
                            <svg v-if="!markingReady" class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ markingReady ? 'Menandai siap...' : 'Tandai Siap' }}
                        </button>
                        <p v-if="!state.permissions.can_mark_ready" class="mt-3 text-sm t-muted">Lengkapi item yang belum terpenuhi untuk melanjutkan.</p>
                    </div>
                </section>
            </div>

            <!-- Serah terima fasilitator -->
            <section v-if="state.session.status === 'ready'" class="card overflow-hidden">
                <div class="grid gap-5 p-5 sm:p-6 md:grid-cols-[auto,1fr,auto] md:items-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl" style="background: var(--ok-bg); color: var(--ok)">
                        <svg class="h-6 w-6" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-display text-xl font-bold t-ink">Sesi Siap</h2>
                        <p class="mt-1 text-sm t-muted">Persiapan selesai. {{ state.facilitator?.name ?? 'Fasilitator yang ditetapkan' }} memulai latihan melalui konsol fasilitator.</p>
                    </div>
                    <Link
                        v-if="state.permissions.can_open_console"
                        :href="route('tenant.ttx.sessions.console', state.session.id)"
                        class="btn btn-primary justify-center md:justify-self-end"
                    >
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Buka Console
                    </Link>
                    <p v-else class="text-sm t-muted md:max-w-xs md:text-right">Fasilitator yang ditetapkan akan melanjutkan sesi dari akunnya.</p>
                </div>
            </section>

            <div v-else-if="state.session.status !== 'draft'" class="flex justify-end">
                <Link
                    v-if="state.permissions.can_open_console"
                    :href="route('tenant.ttx.sessions.console', state.session.id)"
                    class="btn btn-primary"
                >
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Buka Console
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.preparation { overflow-wrap: anywhere; }
.roster-empty { padding: 1.25rem .5rem; }
.back-link { border-radius: .25rem; transition: box-shadow 150ms, opacity 150ms; }
.back-link:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
.back-link:hover { box-shadow: 0 0 0 1px var(--brand); }
.back-link:active { opacity: .75; }
.preparation select { min-width: 0; max-width: 100%; background-color: var(--surface); color: var(--ink); border-color: var(--line); border-radius: .75rem; }
.preparation :is(button, a, select) { transition: background-color 150ms, box-shadow 150ms, opacity 150ms; }
.preparation :is(button, a, select):focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
.preparation :is(button, a, select):hover:not(:disabled) { box-shadow: 0 0 0 1px var(--brand); }
.preparation :is(button, a, select):active:not(:disabled) { opacity: .75; }
.preparation :disabled { cursor: not-allowed; opacity: .6; }
@media (prefers-reduced-motion: reduce) { .preparation, .preparation * { animation: none !important; transition: none !important; } }
</style>
