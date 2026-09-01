<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    assignment: Object,
    quiz: Object,
    activeAttempt: Object,
    alreadyPassed: Boolean,
    passedAttempt: Object,
});

const state = ref('start'); // 'start' | 'attempt' | 'result'
const attemptId = ref(null);
const questions = ref([]);
const answers = ref({});
const currentQuestionIndex = ref(0);
const deadlineAt = ref(null);
const startedAt = ref(null);
const remainingSeconds = ref(0);
const timerInterval = ref(null);
const submitting = ref(false);
const result = ref(null);
const error = ref(null);

const currentQuestion = computed(() => questions.value[currentQuestionIndex.value] || null);

const isAnswered = (questionId) => answers.value[questionId] !== undefined;

const allAnswered = computed(() => {
    return questions.value.every(q => isAnswered(q.id));
});

const formattedTime = computed(() => {
    if (remainingSeconds.value <= 0) return '00:00';
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const timeWarning = computed(() => {
    return remainingSeconds.value > 0 && remainingSeconds.value <= 60;
});

const startQuiz = async () => {
    error.value = null;
    submitting.value = true;

    try {
        const response = await axios.post(route('user.training.quiz.start', props.assignment.id));

        attemptId.value = response.data.attempt_id;
        questions.value = response.data.questions;
        deadlineAt.value = response.data.deadline_at;
        startedAt.value = response.data.started_at;

        state.value = 'attempt';
        startTimer();
    } catch (err) {
        error.value = err.response?.data?.message || 'Gagal memulai quiz.';
    } finally {
        submitting.value = false;
    }
};

const resumeAttempt = async () => {
    error.value = null;
    submitting.value = true;

    try {
        const response = await axios.get(route('user.training.quiz.attempt', props.activeAttempt.id));

        attemptId.value = response.data.attempt_id;
        questions.value = response.data.questions;
        deadlineAt.value = response.data.deadline_at;
        startedAt.value = response.data.started_at;

        state.value = 'attempt';
        startTimer();
    } catch (err) {
        error.value = err.response?.data?.message || 'Gagal melanjutkan quiz.';
    } finally {
        submitting.value = false;
    }
};

const startTimer = () => {
    if (!deadlineAt.value) return;

    const updateRemaining = () => {
        const deadline = new Date(deadlineAt.value);
        const now = new Date();
        const diff = Math.floor((deadline - now) / 1000);
        remainingSeconds.value = Math.max(0, diff);

        if (remainingSeconds.value === 0) {
            clearInterval(timerInterval.value);
            submitQuiz();
        }
    };

    updateRemaining();
    timerInterval.value = setInterval(updateRemaining, 1000);
};

const submitQuiz = async () => {
    if (submitting.value) return;

    error.value = null;
    submitting.value = true;

    if (timerInterval.value) {
        clearInterval(timerInterval.value);
    }

    try {
        const response = await axios.post(route('user.training.quiz.submit', attemptId.value), {
            answers: answers.value,
        });

        result.value = response.data;
        state.value = 'result';
    } catch (err) {
        error.value = err.response?.data?.message || 'Gagal mengirim jawaban.';
        submitting.value = false;
    }
};

const goToQuestion = (index) => {
    currentQuestionIndex.value = index;
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < questions.value.length - 1) {
        currentQuestionIndex.value++;
    }
};

const prevQuestion = () => {
    if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--;
    }
};

onMounted(() => {
    if (props.activeAttempt && !props.alreadyPassed) {
        resumeAttempt();
    }
});

onUnmounted(() => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value);
    }
});
</script>

<template>
    <Head :title="'Quiz: ' + assignment.module_title" />

    <AppLayout :title="'Quiz: ' + assignment.module_title">
        <div class="mb-6">
            <Link :href="route('user.training.index')" class="text-sm hover:underline transition-colors" style="color: var(--t-link);">
                ← Kembali ke Daftar Training
            </Link>
        </div>

        <!-- State: Start -->
        <div v-if="state === 'start'" class="card p-8">
            <h2 class="text-2xl font-display mb-6" style="color: var(--t-ink);">{{ quiz.title }}</h2>

            <div v-if="alreadyPassed" class="space-y-6">
                <div class="flex items-start gap-4 p-4 rounded-xl" style="background: var(--success-bg); border: 1px solid var(--success-border);">
                    <svg class="w-6 h-6 flex-shrink-0 mt-0.5" style="color: var(--success);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <div class="font-semibold mb-1" style="color: var(--success);">Anda sudah lulus quiz ini</div>
                        <div class="text-sm" style="color: var(--t-muted);">
                            Skor: {{ passedAttempt.score }}% pada {{ new Date(passedAttempt.submitted_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('user.quiz.result', passedAttempt.id)" class="btn btn-primary">
                        Lihat Hasil
                    </Link>
                    <Link :href="route('user.training.index')" class="btn">
                        Kembali
                    </Link>
                </div>
            </div>

            <div v-else class="space-y-6">
                <div class="space-y-3">
                    <div class="flex items-center gap-3 text-sm">
                        <svg class="w-5 h-5" style="color: var(--t-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span style="color: var(--t-ink);">{{ quiz.question_count }} soal</span>
                    </div>

                    <div v-if="quiz.duration_minutes" class="flex items-center gap-3 text-sm">
                        <svg class="w-5 h-5" style="color: var(--t-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span style="color: var(--t-ink);">{{ quiz.duration_minutes }} menit</span>
                    </div>

                    <div class="flex items-center gap-3 text-sm">
                        <svg class="w-5 h-5" style="color: var(--t-muted);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span style="color: var(--t-ink);">Nilai kelulusan: {{ quiz.passing_score }}%</span>
                    </div>
                </div>

                <div class="p-4 rounded-xl" style="background: var(--bg-subtle); border: 1px solid var(--b-line);">
                    <div class="font-medium mb-2" style="color: var(--t-ink);">Aturan Quiz:</div>
                    <ul class="space-y-1 text-sm" style="color: var(--t-muted);">
                        <li>• Semua soal harus dijawab</li>
                        <li v-if="quiz.duration_minutes">• Quiz akan otomatis terkirim saat waktu habis</li>
                        <li>• Urutan soal dan opsi jawaban diacak untuk setiap peserta</li>
                        <li>• Quiz hanya dapat dikerjakan sekali setelah lulus</li>
                    </ul>
                </div>

                <div v-if="error" class="p-4 rounded-xl" style="background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger);">
                    {{ error }}
                </div>

                <button @click="startQuiz" :disabled="submitting" class="btn btn-primary">
                    <span v-if="submitting">Memproses...</span>
                    <span v-else>Mulai Quiz</span>
                </button>
            </div>
        </div>

        <!-- State: Attempt -->
        <div v-if="state === 'attempt'" class="space-y-6">
            <!-- Timer & Progress -->
            <div class="card p-6">
                <div class="flex items-center justify-between gap-6 flex-wrap">
                    <div>
                        <div class="text-sm mb-1" style="color: var(--t-muted);">Progres</div>
                        <div class="font-semibold" style="color: var(--t-ink);">{{ currentQuestionIndex + 1 }} dari {{ questions.length }}</div>
                    </div>

                    <div v-if="deadlineAt">
                        <div class="text-sm mb-1" style="color: var(--t-muted);">Waktu tersisa</div>
                        <div class="text-2xl font-display font-bold" :style="{ color: timeWarning ? 'var(--danger)' : 'var(--t-ink)' }">
                            {{ formattedTime }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question -->
            <div v-if="currentQuestion" class="card p-8">
                <div class="mb-6">
                    <div class="text-sm font-medium mb-2" style="color: var(--t-muted);">Soal {{ currentQuestionIndex + 1 }}</div>
                    <div class="text-lg font-medium" style="color: var(--t-ink);">{{ currentQuestion.question }}</div>
                </div>

                <div class="space-y-3">
                    <label
                        v-for="(opt, oi) in currentQuestion.options"
                        :key="oi"
                        class="flex items-start gap-3 px-5 py-4 rounded-xl border cursor-pointer transition-all duration-150"
                        :class="{
                            'hover:shadow-sm': true,
                        }"
                        :style="{
                            background: answers[currentQuestion.id] === oi ? 'var(--primary-bg)' : 'var(--bg-canvas)',
                            borderColor: answers[currentQuestion.id] === oi ? 'var(--primary)' : 'var(--b-line)',
                        }"
                    >
                        <input
                            type="radio"
                            :name="'q' + currentQuestion.id"
                            :value="oi"
                            v-model="answers[currentQuestion.id]"
                            class="mt-1 flex-shrink-0"
                            style="accent-color: var(--primary);"
                        />
                        <div class="text-sm" style="color: var(--t-ink);">
                            <span class="font-semibold">{{ String.fromCharCode(65 + oi) }}.</span> {{ opt }}
                        </div>
                    </label>
                </div>
            </div>

            <!-- Navigation -->
            <div class="card p-6">
                <div class="flex items-center justify-between gap-6 flex-wrap mb-6">
                    <button @click="prevQuestion" :disabled="currentQuestionIndex === 0" class="btn">
                        ← Sebelumnya
                    </button>
                    <button v-if="currentQuestionIndex < questions.length - 1" @click="nextQuestion" class="btn btn-primary">
                        Selanjutnya →
                    </button>
                    <button v-else @click="submitQuiz" :disabled="submitting || !allAnswered" class="btn btn-primary">
                        <span v-if="submitting">Mengirim...</span>
                        <span v-else>Selesai</span>
                    </button>
                </div>

                <!-- Question Navigator -->
                <div class="border-t pt-6" style="border-color: var(--b-line);">
                    <div class="text-sm font-medium mb-3" style="color: var(--t-muted);">Navigasi Soal</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(q, qi) in questions"
                            :key="qi"
                            @click="goToQuestion(qi)"
                            class="w-10 h-10 rounded-lg text-sm font-medium transition-all duration-150"
                            :style="{
                                background: qi === currentQuestionIndex
                                    ? 'var(--primary)'
                                    : isAnswered(q.id)
                                    ? 'var(--success-bg)'
                                    : 'var(--bg-subtle)',
                                color: qi === currentQuestionIndex
                                    ? 'white'
                                    : isAnswered(q.id)
                                    ? 'var(--success)'
                                    : 'var(--t-muted)',
                                borderColor: qi === currentQuestionIndex ? 'var(--primary)' : 'var(--b-line)',
                                borderWidth: '1px',
                            }"
                        >
                            {{ qi + 1 }}
                        </button>
                    </div>

                    <div v-if="!allAnswered" class="mt-4 text-sm" style="color: var(--t-muted);">
                        {{ questions.filter(q => isAnswered(q.id)).length }} dari {{ questions.length }} soal terjawab
                    </div>
                </div>
            </div>

            <div v-if="error" class="card p-4" style="background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger);">
                {{ error }}
            </div>
        </div>

        <!-- State: Result -->
        <div v-if="state === 'result'" class="card p-8">
            <div class="text-center mb-8">
                <div v-if="result.passed" class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4" style="background: var(--success-bg);">
                    <svg class="w-10 h-10" style="color: var(--success);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div v-else class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-4" style="background: var(--danger-bg);">
                    <svg class="w-10 h-10" style="color: var(--danger);" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>

                <h2 class="text-3xl font-display font-bold mb-2" style="color: var(--t-ink);">
                    {{ result.passed ? 'Selamat!' : 'Belum Lulus' }}
                </h2>
                <p class="text-lg" style="color: var(--t-muted);">
                    {{ result.passed ? 'Anda lulus quiz ini.' : 'Anda belum mencapai nilai kelulusan.' }}
                </p>

                <div class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-xl" :style="{ background: result.passed ? 'var(--success-bg)' : 'var(--danger-bg)' }">
                    <span class="text-sm" :style="{ color: result.passed ? 'var(--success)' : 'var(--danger)' }">Skor Anda:</span>
                    <span class="text-3xl font-display font-bold" :style="{ color: result.passed ? 'var(--success)' : 'var(--danger)' }">{{ result.score }}%</span>
                </div>

                <div v-if="result.status === 'expired'" class="mt-4 text-sm" style="color: var(--t-muted);">
                    (Waktu habis - jawaban otomatis terkirim)
                </div>
            </div>

            <div class="flex justify-center gap-3">
                <Link :href="route('user.quiz.result', result.attempt_id)" class="btn btn-primary">
                    Lihat Detail Hasil
                </Link>
                <Link v-if="!result.passed" :href="route('user.training.quiz', assignment.id)" class="btn">
                    Coba Lagi
                </Link>
                <Link v-else :href="route('user.training.index')" class="btn">
                    Kembali ke Training
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
