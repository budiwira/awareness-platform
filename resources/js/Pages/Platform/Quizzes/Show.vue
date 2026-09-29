<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ quiz: Object, preview: { type: Boolean, default: false } });
const errors = computed(() => usePage().props.errors ?? {});
const processing = ref(null);
const editingQuestion = ref(null);
const quizForm = ref({
    purpose: props.quiz.purpose, title: props.quiz.title,
    passing_score: props.quiz.passing_score ?? 1,
    duration_minutes: props.quiz.duration_minutes, is_active: props.quiz.is_active,
});
const emptyQuestion = () => ({ question: '', options: ['', ''], correct_index: 0, explanation: '' });
const questionForm = ref(emptyQuestion());

const resetQuestion = () => {
    editingQuestion.value = null;
    questionForm.value = emptyQuestion();
};
const editQuestion = (question) => {
    editingQuestion.value = question.id;
    questionForm.value = {
        question: question.question, options: [...question.options],
        correct_index: question.correct_index, explanation: question.explanation ?? '',
    };
};
const addOption = () => {
    if (questionForm.value.options.length < 6) questionForm.value.options.push('');
};
const removeOption = (index) => {
    if (questionForm.value.options.length <= 2) return;
    questionForm.value.options.splice(index, 1);
    if (questionForm.value.correct_index >= questionForm.value.options.length) questionForm.value.correct_index = 0;
};
const saveQuiz = () => {
    processing.value = 'quiz';
    router.patch(route('platform.quizzes.update', props.quiz.id), quizForm.value, { onFinish: () => (processing.value = null) });
};
const saveQuestion = () => {
    processing.value = 'question';
    const done = { onSuccess: resetQuestion, onFinish: () => (processing.value = null), preserveScroll: true };
    if (editingQuestion.value) {
        router.patch(route('platform.quizzes.questions.update', [props.quiz.id, editingQuestion.value]), questionForm.value, done);
    } else {
        router.post(route('platform.quizzes.questions.store', props.quiz.id), questionForm.value, done);
    }
};
const removeQuestion = (id) => {
    if (confirm('Hapus pertanyaan ini?')) router.delete(route('platform.quizzes.questions.destroy', [props.quiz.id, id]), { preserveScroll: true });
};
const replaceQuiz = () => {
    processing.value = 'replace';
    router.post(route('platform.quizzes.replace', props.quiz.id), {}, { onFinish: () => (processing.value = null) });
};
const bindQuiz = () => {
    processing.value = 'bind';
    router.post(route('platform.quizzes.bind', props.quiz.id), {}, { onFinish: () => (processing.value = null) });
};
const removeQuiz = () => {
    if (confirm('Hapus assessment draft ini secara permanen?')) router.delete(route('platform.quizzes.destroy', props.quiz.id));
};
const purposeLabel = computed(() => ({ pretest: 'Pretest / Baseline', posttest: 'Posttest / Final', practice: 'Latihan' }[props.quiz.purpose]));
</script>

<template>
    <Head :title="quiz.title" />

    <AppLayout :title="preview ? `Preview: ${quiz.title}` : quiz.title">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <Link :href="route('platform.quizzes.index')" class="text-sm font-medium text-indigo-600 hover:underline focus-visible:outline-none focus-visible:ring-2">Kembali ke Assessment</Link>
            <div class="flex flex-wrap gap-2">
                <Link v-if="!preview" :href="route('platform.quizzes.preview', quiz.id)" class="btn btn-secondary">Preview</Link>
                <Link v-else :href="route('platform.quizzes.show', quiz.id)" class="btn btn-secondary">Kembali Kelola</Link>
                <button v-if="!preview && quiz.is_frozen" class="btn btn-primary" :disabled="processing === 'replace'" @click="replaceQuiz">{{ processing === 'replace' ? 'Membuat...' : 'Buat Pengganti' }}</button>
                <button v-if="!preview && !quiz.is_current && quiz.purpose !== 'practice' && quiz.is_active" class="btn btn-primary" :disabled="processing === 'bind'" @click="bindQuiz">{{ processing === 'bind' ? 'Mengikat...' : 'Jadikan Aktif' }}</button>
            </div>
        </div>

        <div v-if="errors.quiz" class="card mb-6 border-l-4 p-4 text-sm text-red-700" role="alert">{{ errors.quiz }}</div>

        <section class="card p-6 mb-6 fade-in">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide t-muted">{{ purposeLabel }}</p>
                    <h2 class="mt-1 font-display text-2xl font-bold t-ink">{{ quiz.title }}</h2>
                    <p class="mt-2 text-sm t-muted">Modul: {{ quiz.module?.title }} · {{ quiz.duration_minutes ?? 'Tanpa batas' }} menit</p>
                    <p v-if="quiz.purpose === 'posttest'" class="mt-1 text-sm t-muted">Passing score: {{ quiz.passing_score }}%</p>
                    <p v-if="quiz.purpose === 'pretest'" class="mt-1 text-sm t-muted">Baseline pengetahuan; tidak menggunakan makna lulus/gagal.</p>
                </div>
                <div class="flex gap-2">
                    <span class="rounded-full px-3 py-1 text-xs" :class="quiz.is_current ? 'badge-ok' : 'bg-surface2 t-muted'">{{ quiz.is_current ? 'Aktif' : 'Historis' }}</span>
                    <span class="rounded-full px-3 py-1 text-xs" :class="quiz.is_frozen ? 'badge-warn' : 'chip-brand'">{{ quiz.is_frozen ? 'Frozen' : 'Mutable' }}</span>
                </div>
            </div>
        </section>

        <form v-if="!preview && quiz.is_mutable" class="card p-6 mb-8 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="saveQuiz">
            <h3 class="md:col-span-2 font-display text-lg font-bold t-ink">Konfigurasi Assessment</h3>
            <div>
                <label for="quiz-title" class="text-sm font-medium t-ink">Judul</label>
                <input id="quiz-title" v-model="quizForm.title" required class="input mt-1 w-full" />
            </div>
            <div>
                <label for="quiz-duration" class="text-sm font-medium t-ink">Durasi (menit)</label>
                <input id="quiz-duration" v-model.number="quizForm.duration_minutes" type="number" min="1" max="1440" class="input mt-1 w-full" />
            </div>
            <div>
                <label for="quiz-purpose" class="text-sm font-medium t-ink">Jenis</label>
                <select id="quiz-purpose" v-model="quizForm.purpose" class="input mt-1 w-full">
                    <option value="pretest">Pretest / Baseline</option><option value="posttest">Posttest / Final</option><option value="practice">Latihan</option>
                </select>
            </div>
            <div v-if="quizForm.purpose !== 'pretest'">
                <label for="quiz-passing" class="text-sm font-medium t-ink">Passing score (%)</label>
                <input id="quiz-passing" v-model.number="quizForm.passing_score" type="number" min="1" max="100" required class="input mt-1 w-full" />
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm t-ink"><input v-model="quizForm.is_active" type="checkbox" /> Assessment dapat digunakan</label>
            <div class="md:col-span-2 flex justify-between gap-3">
                <button v-if="!quiz.is_current" type="button" class="btn btn-danger" @click="removeQuiz">Hapus Draft</button><span v-else></span>
                <button class="btn btn-primary" :disabled="processing === 'quiz'">{{ processing === 'quiz' ? 'Menyimpan...' : 'Simpan Konfigurasi' }}</button>
            </div>
        </form>

        <div v-if="quiz.is_frozen && !preview" class="card mb-8 p-5 text-sm t-muted">
            Assessment ini sudah digunakan. Konten semantik dikunci untuk menjaga assignment dan hasil historis. Gunakan <strong>Buat Pengganti</strong> untuk revisi.
        </div>

        <section class="space-y-4 mb-8">
            <div class="flex items-center justify-between">
                <h3 class="font-display text-lg font-bold t-ink">Pertanyaan ({{ quiz.questions?.length ?? 0 }})</h3>
            </div>
            <article v-for="(question, index) in quiz.questions" :key="question.id" class="card p-6 fade-in">
                <div class="flex items-start justify-between gap-4">
                    <h4 class="font-semibold t-ink">{{ index + 1 }}. {{ question.question }}</h4>
                    <div v-if="!preview && quiz.is_mutable" class="flex gap-2">
                        <button type="button" class="btn btn-secondary text-sm" @click="editQuestion(question)">Edit</button>
                        <button type="button" class="btn btn-danger text-sm" @click="removeQuestion(question.id)">Hapus</button>
                    </div>
                </div>
                <ul class="mt-4 space-y-2">
                    <li v-for="(option, optionIndex) in question.options" :key="optionIndex" class="rounded-lg px-3 py-2 text-sm" :class="optionIndex === question.correct_index ? 'badge-ok' : 'bg-surface2 t-muted'">
                        {{ String.fromCharCode(65 + optionIndex) }}. {{ option }} <span v-if="optionIndex === question.correct_index" class="ml-2 text-xs font-semibold">Kunci</span>
                    </li>
                </ul>
                <p v-if="question.explanation" class="mt-4 rounded-lg bg-surface2 p-3 text-sm t-muted"><strong>Penjelasan:</strong> {{ question.explanation }}</p>
            </article>
            <div v-if="(quiz.questions?.length ?? 0) === 0" class="card p-10 text-center t-muted">Belum ada pertanyaan. Assessment belum siap dipublikasikan.</div>
        </section>

        <form v-if="!preview && quiz.is_mutable" class="card p-6 space-y-4" @submit.prevent="saveQuestion">
            <h3 class="font-display text-lg font-bold t-ink">{{ editingQuestion ? 'Edit Pertanyaan' : 'Tambah Pertanyaan' }}</h3>
            <div>
                <label for="question-text" class="text-sm font-medium t-ink">Pertanyaan</label>
                <textarea id="question-text" v-model="questionForm.question" required maxlength="2000" rows="3" class="input mt-1 w-full"></textarea>
            </div>
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium t-ink">Pilihan jawaban (2–6)</legend>
                <div v-for="(option, index) in questionForm.options" :key="index" class="flex items-center gap-3">
                    <input v-model="questionForm.correct_index" type="radio" :value="index" :aria-label="`Jadikan pilihan ${index + 1} sebagai kunci`" />
                    <input v-model="questionForm.options[index]" required maxlength="500" class="input flex-1" :placeholder="`Pilihan ${index + 1}`" />
                    <button type="button" class="btn btn-secondary" :disabled="questionForm.options.length <= 2" @click="removeOption(index)">Hapus</button>
                </div>
                <button type="button" class="btn btn-secondary" :disabled="questionForm.options.length >= 6" @click="addOption">Tambah Pilihan</button>
            </fieldset>
            <div>
                <label for="question-explanation" class="text-sm font-medium t-ink">Penjelasan (opsional)</label>
                <textarea id="question-explanation" v-model="questionForm.explanation" maxlength="4000" rows="3" class="input mt-1 w-full"></textarea>
            </div>
            <p v-if="Object.keys(errors).length" class="text-sm text-red-600" role="alert">Periksa kembali data pertanyaan yang ditandai.</p>
            <div class="flex justify-end gap-3">
                <button v-if="editingQuestion" type="button" class="btn btn-secondary" @click="resetQuestion">Batal</button>
                <button class="btn btn-primary" :disabled="processing === 'question'">{{ processing === 'question' ? 'Menyimpan...' : (editingQuestion ? 'Simpan Perubahan' : 'Tambah Pertanyaan') }}</button>
            </div>
        </form>
    </AppLayout>
</template>
