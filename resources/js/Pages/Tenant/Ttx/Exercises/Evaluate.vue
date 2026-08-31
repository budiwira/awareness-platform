<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ exercise: Object, members: Array, scores: Object });

const form = ref({
    aar_notes: props.exercise.aar_notes ?? '',
    corrective_actions: (props.exercise.corrective_actions ?? []).join('\n'),
    scores: { ...props.scores },
});

const submit = () => {
    router.post(route('tenant.ttx.exercises.evaluate.store', props.exercise.id), form.value);
};
</script>

<template>
    <Head :title="'Evaluasi: ' + exercise.title" />

    <AppLayout :title="'Evaluasi & AAR'">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.exercises.show', exercise.id)" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Exercise
            </Link>
        </div>

        <form @submit.prevent="submit" class="max-w-3xl space-y-6">
            <!-- SKOR PER PESERTA -->
            <div class="card p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Skor Peserta (0–100)</h3>
                <div class="space-y-3">
                    <div v-for="m in members" :key="m.id" class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ m.name }}</div>
                            <div class="text-xs text-gray-500">{{ m.email }}</div>
                        </div>
                        <input v-model.number="form.scores[m.id]" type="number" min="0" max="100"
                            class="input w-24" placeholder="—" />
                    </div>
                    <p v-if="members.length === 0" class="text-sm text-gray-500">Belum ada anggota tim.</p>
                </div>
            </div>

            <!-- AAR -->
            <div class="card p-6">
                <h3 class="font-semibold text-gray-800 mb-4">After-Action Review (AAR)</h3>
                <label class="text-sm text-gray-600">Catatan Debriefing</label>
                <textarea v-model="form.aar_notes" rows="4"
                    placeholder="Celah SOP, kesiapan tim, koordinasi antar tim..."
                    class="input mt-1 w-full"></textarea>

                <label class="text-sm text-gray-600 mt-4 block">Corrective Action Plan (satu per baris)</label>
                <textarea v-model="form.corrective_actions" rows="4"
                    placeholder="Perbarui SOP eskalasi&#10;Latihan komunikasi eksternal tiap kuartal"
                    class="input mt-1 w-full"></textarea>
            </div>

            <div class="flex justify-end">
                <button class="btn btn-primary">
                    Simpan Evaluasi & Selesaikan Exercise
                </button>
            </div>
        </form>
    </AppLayout>
</template>