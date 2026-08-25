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

        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ quiz.title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Modul: {{ quiz.module?.title }} · Passing: {{ quiz.passing_score }}%</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                    {{ quiz.questions?.length ?? 0 }} soal
                </span>
            </div>
        </div>

        <!-- Daftar pertanyaan (kunci jawaban HANYA terlihat oleh super admin) -->
        <div class="space-y-4 mb-8">
            <div v-for="(q, qi) in quiz.questions" :key="q.id" class="bg-white rounded-xl shadow-sm p-6">
                <div class="font-medium text-gray-900 mb-3">{{ qi + 1 }}. {{ q.question }}</div>
                <ul class="space-y-1">
                    <li
                        v-for="(opt, oi) in q.options"
                        :key="oi"
                        class="text-sm px-3 py-1.5 rounded-lg"
                        :class="oi === q.correct_index ? 'bg-emerald-50 text-emerald-700 font-medium' : 'text-gray-600'"
                    >
                        {{ String.fromCharCode(65 + oi) }}. {{ opt }}
                        <span v-if="oi === q.correct_index" class="ml-2 text-[10px] uppercase bg-emerald-100 px-2 py-0.5 rounded-full">kunci</span>
                    </li>
                </ul>
            </div>
            <div v-if="(quiz.questions?.length ?? 0) === 0" class="bg-white rounded-xl shadow-sm p-8 text-center text-gray-500 text-sm">
                Belum ada pertanyaan. Tambahkan di bawah.
            </div>
        </div>

        <!-- Form tambah pertanyaan -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="font-semibold text-gray-800 mb-4">Tambah Pertanyaan</div>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="text-sm text-gray-600">Pertanyaan</label>
                    <textarea v-model="form.question" rows="2" required class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                    <p v-if="errors.question" class="text-xs text-red-600 mt-1">{{ errors.question }}</p>
                </div>

                <div>
                    <label class="text-sm text-gray-600">Pilihan Jawaban (tandai kunci dengan radio)</label>
                    <div class="mt-1 space-y-2">
                        <div v-for="(opt, i) in form.options" :key="i" class="flex items-center gap-2">
                            <input type="radio" :value="i" v-model="form.correct_index" class="text-indigo-600" :title="'Jadikan ' + String.fromCharCode(65 + i) + ' kunci jawaban'" />
                            <input v-model="form.options[i]" type="text" required :placeholder="'Pilihan ' + String.fromCharCode(65 + i)" class="flex-1 rounded-lg border-gray-300 text-sm" />
                            <button type="button" @click="removeOption(i)" class="text-red-500 text-sm" v-if="form.options.length > 2">Hapus</button>
                        </div>
                    </div>
                    <button type="button" @click="addOption" class="mt-2 text-indigo-600 text-sm font-medium">+ Tambah Pilihan</button>
                    <p v-if="errors.options" class="text-xs text-red-600 mt-1">{{ errors.options }}</p>
                    <p v-if="errors.correct_index" class="text-xs text-red-600 mt-1">{{ errors.correct_index }}</p>
                </div>

                <div class="flex justify-end">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan Pertanyaan</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>