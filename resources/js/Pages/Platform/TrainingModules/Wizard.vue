<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import QuillEditor from '@/Components/QuillEditor.vue';
import { BaseButton, BaseInput, BaseSelect, BaseTextarea } from '@/Components';

const props = defineProps({
    module: Object,
    quizzes: { type: Array, default: () => [] },
});

const errors = computed(() => usePage().props.errors ?? {});
const isEdit = computed(() => props.module !== null);

const step = ref(1);
const submitting = ref(false);
const form = ref({
    title: props.module?.title ?? '',
    description: props.module?.description ?? '',
    duration_minutes: props.module?.duration_minutes ?? 15,
    content_html: props.module?.content_html ?? '',
    pretest_quiz_id: props.module?.pretest_quiz_id ?? null,
    posttest_quiz_id: props.module?.posttest_quiz_id ?? null,
    status: props.module?.status ?? 'draft',
    is_active: props.module?.is_active ?? true,
});

const steps = [
    { num: 1, label: 'Info Dasar' },
    { num: 2, label: 'Materi' },
    { num: 3, label: 'Evaluasi' },
    { num: 4, label: 'Review & Publish' },
];

const plainText = computed(() => form.value.content_html.replace(/<[^>]*>/g, '').trim());

const canNext = computed(() => {
    if (step.value === 1) return !!form.value.title && form.value.duration_minutes > 0;
    if (step.value === 2) return plainText.value.length > 0;
    return true;
});

const quizTitle = (id) => props.quizzes.find((q) => q.id === id)?.title ?? '-';

const next = () => {
    if (canNext.value && step.value < 4) step.value++;
};

const prev = () => {
    if (step.value > 1) step.value--;
};

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    const options = { onFinish: () => (submitting.value = false) };
    if (isEdit.value) {
        router.patch(route('platform.modules.update', props.module.id), form.value, options);
    } else {
        router.post(route('platform.modules.store'), form.value, options);
    }
};

// Upload gambar: module-scoped saat edit, module-agnostic saat create
const uploadHandler = async (file) => {
    const formData = new FormData();
    formData.append('file', file);

    const endpoint = isEdit.value
        ? route('platform.modules.media.store', props.module.id)
        : route('platform.media.store');

    try {
        const response = await axios.post(endpoint, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        return response.data.url;
    } catch (err) {
        throw new Error(err.response?.data?.message ?? 'Upload ditolak server');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Modul' : 'Buat Modul Baru'" />

    <AppLayout :title="isEdit ? 'Edit Modul' : 'Buat Modul Baru'">
        <div class="flex items-center justify-center gap-2 mb-8">
            <div v-for="s in steps" :key="s.num" class="flex items-center gap-2">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold transition-all"
                    :class="step >= s.num ? 'chip-active' : 'bg-surface2 t-muted'"
                >
                    {{ s.num }}
                </div>
                <span class="text-sm font-medium" :class="step >= s.num ? 't-ink' : 't-muted'">{{ s.label }}</span>
                <svg v-if="s.num < 4" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 t-muted ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>

        <div class="card p-8 max-w-4xl mx-auto">
            <div v-if="step === 1" class="space-y-4 fade-in">
                <BaseInput v-model="form.title" label="Judul Modul" required placeholder="Contoh: Phishing Awareness Fundamentals" :error="errors.title" />
                <BaseInput v-model.number="form.duration_minutes" label="Durasi (menit)" type="number" required :error="errors.duration_minutes" />
                <BaseTextarea v-model="form.description" label="Deskripsi Singkat" :rows="3" placeholder="Ringkasan untuk katalog..." :error="errors.description" />
            </div>

            <div v-if="step === 2" class="space-y-4 fade-in">
                <p class="text-sm t-muted mb-4">
                    Tulis materi dengan rich text editor. Toolbar: format teks, list, gambar (upload ke storage private), video (embed YouTube/Vimeo).
                </p>
                <QuillEditor v-model="form.content_html" :upload-handler="uploadHandler" placeholder="Tulis materi modul di sini..." />
                <p v-if="errors.content_html" class="text-xs mt-1" style="color: var(--danger)" role="alert">{{ errors.content_html }}</p>
            </div>

            <div v-if="step === 3" class="space-y-4 fade-in">
                <p v-if="quizzes.length === 0" class="text-sm t-muted">Kuis pretest/posttest ditetapkan setelah modul dibuat, lewat halaman Edit modul ini (kuis wajib terikat pada modul).</p>
                <BaseSelect v-model="form.pretest_quiz_id" label="Pretest (opsional)" hint="Baseline pengetahuan sebelum materi. Tidak menghitung score akhir." :error="errors.pretest_quiz_id" :disabled="!isEdit">
                        <option :value="null">Tanpa pretest</option>
                        <option v-for="quiz in quizzes.filter(q => q.purpose === 'pretest')" :key="'pre-' + quiz.id" :value="quiz.id">
                            {{ quiz.title }} (passing {{ quiz.passing_score }}%)
                        </option>
                </BaseSelect>
                <BaseSelect v-model="form.posttest_quiz_id" label="Posttest" hint="Sumber score akhir modul dan learning gain." :error="errors.posttest_quiz_id" :disabled="!isEdit">
                        <option :value="null">Tanpa posttest</option>
                        <option v-for="quiz in quizzes.filter(q => q.purpose === 'posttest')" :key="'post-' + quiz.id" :value="quiz.id">
                            {{ quiz.title }} (passing {{ quiz.passing_score }}%)
                        </option>
                </BaseSelect>
                <div class="rounded-lg p-4 text-sm bg-surface2 t-muted">
                    Belum punya quiz? Buat di halaman <a :href="route('platform.quizzes.index')" class="font-semibold underline">Quizzes</a>, lalu kembali ke wizard ini.
                </div>
            </div>

            <div v-if="step === 4" class="space-y-6 fade-in">
                <div class="bg-app border b-line rounded-lg p-6">
                    <div class="font-display text-xl font-bold t-ink mb-2">{{ form.title }}</div>
                    <div class="text-sm t-muted mb-4">{{ form.description }}</div>
                    <div class="flex flex-wrap items-center gap-4 text-xs t-muted">
                        <span>{{ form.duration_minutes }} menit</span>
                        <span>{{ plainText.length }} karakter materi</span>
                        <span v-if="form.pretest_quiz_id">Pretest: {{ quizTitle(form.pretest_quiz_id) }}</span>
                        <span v-if="form.posttest_quiz_id">Posttest: {{ quizTitle(form.posttest_quiz_id) }}</span>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium t-ink">Status Publikasi</label>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.status" value="draft" class="chip-brand" />
                            <div>
                                <div class="text-sm font-medium t-ink">Draft</div>
                                <div class="text-xs t-muted">Tidak terlihat tenant.</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.status" value="published" class="chip-brand" />
                            <div>
                                <div class="text-sm font-medium t-ink">Published</div>
                                <div class="text-xs t-muted">Tenant bisa menugaskan ke user.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-between mt-8 pt-6 border-t b-line">
                <BaseButton v-if="step > 1" variant="secondary" :disabled="submitting" @click="prev">Sebelumnya</BaseButton>
                <span v-else></span>
                <BaseButton v-if="step < 4" :disabled="!canNext || submitting" @click="next">Selanjutnya</BaseButton>
                <BaseButton v-else :loading="submitting" @click="submit">{{ submitting ? 'Menyimpan...' : (isEdit ? 'Update Modul' : 'Simpan Modul') }}</BaseButton>
            </div>
        </div>
    </AppLayout>
</template>
