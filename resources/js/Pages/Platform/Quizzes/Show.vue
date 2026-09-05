<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ quiz: Object });

const errors = computed(() => usePage().props.errors ?? {});

const form = ref({ question: '', options: ['', ''], correct_index: 0 });

const addOption = () => form.value.options.push('');

const removeOption = (i) => {
    if (form.value.options.length <= 2) return;
    form.value.options.splice(i, 1);
    if (form.value.correct_index >= form.value.options.length) form.value.correct_index = 0;
};

const submit = () => {
    router.post(route('platform.quizzes.questions.store', props.quiz.id), form.value, {
        onSuccess: () => (form.value = { question: '', options: ['', ''], correct_index: 0 }),
    });
};
</script>

<template>
    <Head :title="quiz.title" />

    <AppLayout :title="quiz.title">
        <div class="mb-6">
            <Link :href="route('platform.quizzes.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Quiz
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold t-ink">{{ quiz.title }}</h2>
                    <p class="text-sm t-muted mt-1">Modul: {{ quiz.module?.title }} · Passing: {{ quiz.passing_score }}%</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                    {{ quiz.questions?.length ?? 0 }} soal
                </span>
            </div>
        </div>

        <!-- Daftar pertanyaan (kunci jawaban HANYA terlihat oleh super admin) -->
        <div class="space-y-4 mb-8">
            <div v-for="(q, qi) in quiz.questions" :key="q.id" class="card p-6">
                <div class="font-medium t-ink mb-3">{{ qi + 1 }}. {{ q.question }}</div>
                <ul class="space-y-1">
                    <li
                        v-for="(opt, oi) in q.options"
                        :key="oi"
                        class="text-sm px-3 py-1.5 rounded-lg"
                        :class="oi === q.correct_index ? 'badge-ok badge-ok font-medium' : 't-muted'"
                    >
                        {{ String.fromCharCode(65 + oi) }}. {{ opt }}
                        <span v-if="oi === q.correct_index" class="ml-2 text-[10px] uppercase px-2 py-0.5 rounded-full" style="background: var(--ok-bg); color: var(--ok)">kunci</span>
                    </li>
                </ul>
            </div>
            <div v-if="(quiz.questions?.length ?? 0) === 0" class="card p-8 text-center t-muted text-sm">
                Belum ada pertanyaan. Tambahkan di bawah.
            </div>
        </div>

        <!-- Form tambah pertanyaan -->
        <div class="card p-6">
            <div class="font-semibold t-ink mb-4">Tambah Pertanyaan</div>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="text-sm t-muted">Pertanyaan</label>
                    <textarea v-model="form.question" rows="2" required class="input mt-1 w-full"></textarea>
                    <p v-if="errors.question" class="text-xs text-red-600 mt-1">{{ errors.question }}</p>
                </div>

                <div>
                    <label class="text-sm t-muted">Pilihan Jawaban (tandai kunci dengan radio)</label>
                    <div class="mt-1 space-y-2">
                        <div v-for="(opt, i) in form.options" :key="i" class="flex items-center gap-2">
                            <input type="radio" :value="i" v-model="form.correct_index" class="text-indigo-600" :title="'Jadikan ' + String.fromCharCode(65 + i) + ' kunci jawaban'" />
                            <input v-model="form.options[i]" type="text" required :placeholder="'Pilihan ' + String.fromCharCode(65 + i)" class="input flex-1" />
                            <button type="button" @click="removeOption(i)" class="text-red-500 text-sm" v-if="form.options.length > 2">Hapus</button>
                        </div>
                    </div>
                    <button type="button" @click="addOption" class="mt-2 text-indigo-600 text-sm font-medium">+ Tambah Pilihan</button>
                    <p v-if="errors.options" class="text-xs text-red-600 mt-1">{{ errors.options }}</p>
                    <p v-if="errors.correct_index" class="text-xs text-red-600 mt-1">{{ errors.correct_index }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <button class="btn btn-primary">Simpan Pertanyaan</button>
                    <Link :href="route('platform.quizzes.index')" class="btn bg-surface2 t-ink">Selesai</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>