<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ exercises: Array, playbooks: Array, runbooks: Array, phases: Object });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({ title: '', scenario: '', objectives: '', scope: '', playbook_id: '', runbook_id: '', scheduled_at: '' });

const submit = () => {
    router.post(route('tenant.ttx.exercises.store'), form.value, {
        onSuccess: () => (showForm.value = false),
    });
};

const phaseBadge = (p) => ({
    planning: 'bg-surface2 t-ink',
    preparation: 'bg-blue-100 text-blue-700',
    execution: 'bg-yellow-100 text-yellow-700',
    evaluation: 'bg-purple-100 text-purple-700',
    completed: 'badge-ok',
}[p] ?? 'bg-surface2 t-ink');
</script>

<template>
    <Head title="Simulasi TTX" />

    <AppLayout title="Simulasi Tabletop Exercise">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">Rencanakan & kelola simulasi tabletop berdasarkan playbook & runbook.</p>
            <button @click="showForm = !showForm" class="btn btn-primary">
                + Buat Exercise
            </button>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Judul Exercise</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Narasi Skenario</label>
                    <textarea v-model="form.scenario" rows="3" class="input mt-1 w-full"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Tujuan Latihan</label>
                    <textarea v-model="form.objectives" rows="2" class="input mt-1 w-full"></textarea>
                </div>
                <div>
                    <label class="text-sm t-muted">Playbook</label>
                    <select v-model="form.playbook_id" class="input mt-1 w-full">
                        <option value="">-- Tanpa playbook --</option>
                        <option v-for="pb in playbooks" :key="pb.id" :value="pb.id">{{ pb.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm t-muted">Runbook</label>
                    <select v-model="form.runbook_id" class="input mt-1 w-full">
                        <option value="">-- Tanpa runbook --</option>
                        <option v-for="rb in runbooks" :key="rb.id" :value="rb.id">{{ rb.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm t-muted">Ruang Lingkup</label>
                    <input v-model="form.scope" type="text" class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-sm t-muted">Jadwal</label>
                    <input v-model="form.scheduled_at" type="datetime-local" class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Link v-for="ex in exercises" :key="ex.id" :href="route('tenant.ttx.exercises.show', ex.id)"
                class="card p-6 hover:shadow-md transition block">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold t-ink">{{ ex.title }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="phaseBadge(ex.phase)">
                        {{ phases[ex.phase] }}
                    </span>
                </div>
                <p class="text-sm t-muted mb-3">{{ ex.scope }}</p>
                <div class="text-xs t-muted space-y-1">
                    <div>Playbook: {{ ex.playbook?.title ?? '—' }}</div>
                    <div>Runbook: {{ ex.runbook?.title ?? '—' }}</div>
                    <div>Tim: {{ ex.teams?.length ?? 0 }}</div>
                </div>
            </Link>
            <div v-if="exercises.length === 0" class="col-span-2 card p-8 text-center t-muted text-sm">
                Belum ada exercise.
            </div>
        </div>
    </AppLayout>
</template>