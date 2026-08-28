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
    planning: 'bg-gray-100 text-gray-700',
    preparation: 'bg-blue-100 text-blue-700',
    execution: 'bg-yellow-100 text-yellow-700',
    evaluation: 'bg-purple-100 text-purple-700',
    completed: 'bg-emerald-100 text-emerald-700',
}[p] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head title="Simulasi TTX" />

    <AppLayout title="Simulasi Tabletop Exercise">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">Rencanakan & kelola simulasi tabletop berdasarkan playbook & runbook.</p>
            <button @click="showForm = !showForm"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                + Buat Exercise
            </button>
        </div>

        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Judul Exercise</label>
                    <input v-model="form.title" type="text" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Narasi Skenario</label>
                    <textarea v-model="form.scenario" rows="3" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Tujuan Latihan</label>
                    <textarea v-model="form.objectives" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Playbook</label>
                    <select v-model="form.playbook_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">-- Tanpa playbook --</option>
                        <option v-for="pb in playbooks" :key="pb.id" :value="pb.id">{{ pb.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Runbook</label>
                    <select v-model="form.runbook_id" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="">-- Tanpa runbook --</option>
                        <option v-for="rb in runbooks" :key="rb.id" :value="rb.id">{{ rb.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Ruang Lingkup</label>
                    <input v-model="form.scope" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Jadwal</label>
                    <input v-model="form.scheduled_at" type="datetime-local" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Link v-for="ex in exercises" :key="ex.id" :href="route('tenant.ttx.exercises.show', ex.id)"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition block">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900">{{ ex.title }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="phaseBadge(ex.phase)">
                        {{ phases[ex.phase] }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mb-3">{{ ex.scope }}</p>
                <div class="text-xs text-gray-400 space-y-1">
                    <div>Playbook: {{ ex.playbook?.title ?? '—' }}</div>
                    <div>Runbook: {{ ex.runbook?.title ?? '—' }}</div>
                    <div>Tim: {{ ex.teams?.length ?? 0 }}</div>
                </div>
            </Link>
            <div v-if="exercises.length === 0" class="col-span-2 bg-white rounded-xl shadow-sm p-8 text-center text-gray-500 text-sm">
                Belum ada exercise.
            </div>
        </div>
    </AppLayout>
</template>