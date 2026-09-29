<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseAlert, BaseTableContainer } from '@/Components';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    quizzes: Array,
    modules: Array,
    loading: { type: Boolean, default: false },
    loadError: { type: String, default: null },
});

const errors = computed(() => usePage().props.errors ?? {});
const showForm = ref(false);
const processing = ref(false);
const form = ref({
    training_module_id: '', purpose: 'posttest', title: '', passing_score: 70,
    duration_minutes: 30, is_active: true, bind_as_current: false,
});

const submit = () => {
    processing.value = true;
    router.post(route('platform.quizzes.store'), form.value, {
        onFinish: () => (processing.value = false),
    });
};

const purposeLabel = (purpose) => ({ pretest: 'Pretest / Baseline', posttest: 'Posttest / Final', practice: 'Latihan' }[purpose] ?? purpose);
</script>

<template>
    <Head title="Assessment" />

    <AppLayout title="Assessment Builder">
        <section class="card p-6 mb-6 fade-in">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-xl font-bold t-ink">Pretest, Posttest, dan Latihan</h2>
                    <p class="mt-1 text-sm t-muted">Kelola assessment aktif dan versi historis. Assessment yang sudah digunakan tetap dapat dibaca, tetapi tidak dapat diubah.</p>
                </div>
                <button type="button" class="btn btn-primary" @click="showForm = !showForm">
                    {{ showForm ? 'Tutup Form' : 'Buat Assessment' }}
                </button>
            </div>
        </section>

        <form v-if="showForm" class="card p-6 mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 fade-in" @submit.prevent="submit">
            <div>
                <label for="assessment-module" class="text-sm font-medium t-ink">Modul</label>
                <select id="assessment-module" v-model="form.training_module_id" required class="input mt-1 w-full">
                    <option value="" disabled>Pilih modul</option>
                    <option v-for="item in modules" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
                <p v-if="errors.training_module_id" class="mt-1 text-xs" style="color: var(--danger)" role="alert">{{ errors.training_module_id }}</p>
            </div>
            <div>
                <label for="assessment-purpose" class="text-sm font-medium t-ink">Jenis assessment</label>
                <select id="assessment-purpose" v-model="form.purpose" required class="input mt-1 w-full">
                    <option value="pretest">Pretest / Baseline</option>
                    <option value="posttest">Posttest / Final</option>
                    <option value="practice">Latihan</option>
                </select>
            </div>
            <div>
                <label for="assessment-title" class="text-sm font-medium t-ink">Judul</label>
                <input id="assessment-title" v-model="form.title" required maxlength="255" class="input mt-1 w-full" />
                <p v-if="errors.title" class="mt-1 text-xs" style="color: var(--danger)" role="alert">{{ errors.title }}</p>
            </div>
            <div>
                <label for="assessment-duration" class="text-sm font-medium t-ink">Durasi (menit)</label>
                <input id="assessment-duration" v-model.number="form.duration_minutes" type="number" min="1" max="1440" class="input mt-1 w-full" />
            </div>
            <div v-if="form.purpose !== 'pretest'">
                <label for="assessment-passing" class="text-sm font-medium t-ink">Passing score (%)</label>
                <input id="assessment-passing" v-model.number="form.passing_score" type="number" min="1" max="100" required class="input mt-1 w-full" />
                <p v-if="errors.passing_score" class="mt-1 text-xs" style="color: var(--danger)" role="alert">{{ errors.passing_score }}</p>
            </div>
            <label v-if="form.purpose !== 'practice'" class="flex items-center gap-3 self-end min-h-11 text-sm t-ink">
                <input v-model="form.bind_as_current" type="checkbox" /> Jadikan assessment aktif modul
            </label>
            <div class="md:col-span-2 flex justify-end">
                <button class="btn btn-primary" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : 'Simpan Assessment' }}
                </button>
            </div>
        </form>

        <div v-if="loading" class="card space-y-3 p-6" aria-busy="true" aria-label="Memuat assessment">
            <div v-for="row in 5" :key="row" class="skeleton h-12 rounded-xl"></div>
        </div>

        <BaseAlert v-else-if="loadError" variant="danger" title="Assessment tidak dapat dimuat">
            {{ loadError }}
        </BaseAlert>

        <EmptyState
            v-else-if="quizzes.length === 0"
            title="Belum ada assessment"
            message="Buat Pretest, Posttest, atau Latihan pertama untuk sebuah modul."
            class="card"
        />

        <BaseTableContainer v-else>
            <table class="w-full min-w-[860px] text-sm">
                <thead>
                    <tr class="border-b b-line text-left t-muted">
                        <th class="px-6 py-3 font-medium">Assessment</th>
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Jenis</th>
                        <th class="px-6 py-3 text-right font-medium">Soal</th>
                        <th class="px-6 py-3 font-medium">Lifecycle</th>
                        <th class="px-6 py-3 text-right font-medium">Passing</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="quiz in quizzes" :key="quiz.id" class="border-b b-line transition-colors hover:bg-surface2">
                        <td class="px-6 py-4">
                            <Link :href="route('platform.quizzes.show', quiz.id)" class="font-semibold hover:underline focus-visible:outline-none focus-visible:ring-2" style="color: var(--brand)">
                                {{ quiz.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 t-muted">{{ quiz.module?.title }}</td>
                        <td class="px-6 py-4 t-muted">{{ purposeLabel(quiz.purpose) }}</td>
                        <td class="px-6 py-4 text-right tabular-nums t-muted">{{ quiz.questions_count }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs" :class="quiz.is_current ? 'badge-ok' : 'bg-surface2 t-muted'">{{ quiz.is_current ? 'Aktif' : 'Historis' }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs" :class="quiz.is_frozen ? 'badge-warn' : 'chip-brand'">{{ quiz.is_frozen ? 'Frozen' : 'Mutable' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right tabular-nums t-muted">{{ quiz.purpose === 'pretest' ? 'Tidak berlaku' : `${quiz.passing_score}%` }}</td>
                    </tr>
                </tbody>
            </table>
        </BaseTableContainer>
    </AppLayout>
</template>
