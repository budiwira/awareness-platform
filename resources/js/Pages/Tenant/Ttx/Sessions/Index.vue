<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
    playbooks: { type: Array, default: () => [] },
});

const showCreate = ref(false);
const form = useForm({ exercise_id: '', playbook_id: '', title: '', scheduled_at: '' });
const isEmpty = computed(() => props.sessions.length === 0);
const statuses = {
    draft: { label: 'Draft', variant: 'neutral' },
    ready: { label: 'Siap', variant: 'info' },
    in_progress: { label: 'Berlangsung', variant: 'warning' },
    debrief: { label: 'Debrief', variant: 'brand' },
    completed: { label: 'Selesai', variant: 'success' },
};

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    : 'Belum dijadwalkan';

const createSession = () => form.post(route('tenant.ttx.sessions.store', form.exercise_id));
watch(() => form.exercise_id, (exerciseId) => {
    const scenario = props.scenarios.find((item) => String(item.id) === String(exerciseId));
    form.playbook_id = scenario?.recommended_playbook_id ?? '';
});
</script>

<template>
    <Head title="Sessions Tabletop" />
    <AppLayout title="Sessions Tabletop">
        <div class="fade-in space-y-6">
            <header class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: var(--brand)">Tabletop</p>
                    <h1 class="font-display mt-2 text-2xl font-bold t-ink sm:text-3xl">Sessions</h1>
                    <p class="mt-2 max-w-2xl text-sm t-muted">Buat session, siapkan tim dan peserta, lalu fasilitasi exercise hingga hasil akhir.</p>
                </div>
                <button class="btn btn-primary" type="button" @click="showCreate = !showCreate">{{ showCreate ? 'Tutup Form' : 'Buat Session' }}</button>
            </header>

            <form v-if="showCreate" class="card grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="createSession">
                <div class="sm:col-span-2"><h2 class="font-display text-lg font-bold t-ink">Buat Session Baru</h2><p class="mt-1 text-sm t-muted">Anda otomatis menjadi fasilitator untuk session ini.</p></div>
                <label class="text-sm font-medium t-ink">Skenario
                    <select v-model="form.exercise_id" required class="input mt-2 w-full" :disabled="form.processing"><option disabled value="">Pilih skenario</option><option v-for="scenario in scenarios" :key="scenario.id" :value="scenario.id">{{ scenario.title }}</option></select>
                    <span v-if="form.errors.exercise_id" class="mt-1 block text-xs" style="color: var(--danger)">{{ form.errors.exercise_id }}</span>
                </label>
                <label class="text-sm font-medium t-ink">Playbook
                    <select v-model="form.playbook_id" required class="input mt-2 w-full" :disabled="form.processing"><option disabled value="">Pilih playbook</option><option v-for="playbook in playbooks" :key="playbook.id" :value="playbook.id">{{ playbook.title }}</option></select>
                    <span class="mt-1 block text-xs t-muted">Playbook adalah panduan respons organisasi yang menjadi referensi exercise.</span>
                    <span v-if="form.errors.playbook_id" class="mt-1 block text-xs" style="color: var(--danger)">{{ form.errors.playbook_id }}</span>
                </label>
                <label class="text-sm font-medium t-ink">Judul session
                    <input v-model="form.title" required maxlength="255" class="input mt-2 w-full" :disabled="form.processing" placeholder="Contoh: Simulasi Respons Insiden Q4" />
                    <span v-if="form.errors.title" class="mt-1 block text-xs" style="color: var(--danger)">{{ form.errors.title }}</span>
                </label>
                <label class="text-sm font-medium t-ink">Jadwal
                    <input v-model="form.scheduled_at" type="datetime-local" class="input tabletop-datetime mt-2 w-full" :disabled="form.processing" />
                    <span v-if="form.errors.scheduled_at" class="mt-1 block text-xs" style="color: var(--danger)">{{ form.errors.scheduled_at }}</span>
                </label>
                <div class="flex items-end justify-end gap-3"><button type="button" class="btn btn-secondary" :disabled="form.processing" @click="showCreate = false">Batal</button><button class="btn btn-primary" :disabled="form.processing || !form.exercise_id || !form.playbook_id">{{ form.processing ? 'Membuat...' : 'Buat & Siapkan' }}</button></div>
            </form>

            <div v-if="isEmpty" class="card"><EmptyState title="Belum ada session" message="Buat session baru dan pilih skenario untuk memulai." /></div>
            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="session in sessions" :key="session.id" class="card flex flex-col gap-4 p-5 transition hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><h2 class="font-display font-semibold t-ink">{{ session.title }}</h2><p class="mt-1 text-sm t-muted">{{ session.scenario }}</p></div>
                        <BaseBadge :variant="statuses[session.status]?.variant ?? 'neutral'">{{ statuses[session.status]?.label ?? session.status }}</BaseBadge>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="t-muted">Jadwal</dt><dd class="text-right t-ink">{{ formatDate(session.scheduled_at) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="t-muted">Tim</dt><dd class="tabular-nums text-right t-ink">{{ session.team_count }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="t-muted">Peserta</dt><dd class="tabular-nums text-right t-ink">{{ session.participant_count }}</dd></div>
                    </dl>
                    <Link :href="session.action_url" class="btn btn-secondary mt-auto w-full">{{ session.action_label }}</Link>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
