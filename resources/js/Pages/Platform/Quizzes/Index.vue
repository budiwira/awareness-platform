<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ quizzes: Array, modules: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({ training_module_id: '', title: '', passing_score: 70 });

const submit = () => {
    router.post(route('platform.quizzes.store'), form.value, {
        onSuccess: () => (showForm.value = false),
    });
};
</script>

<template>
    <Head title="Quizzes" />

    <AppLayout title="Quiz Builder">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Buat quiz per modul training. Kunci jawaban tidak pernah dikirim ke browser user.
            </p>
            <button
                @click="showForm = !showForm"
                class="btn btn-primary"
            >
                + Buat Quiz
            </button>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="text-sm t-muted">Modul</label>
                    <select v-model="form.training_module_id" required class="input mt-1 w-full">
                        <option value="" disabled>-- Pilih Modul --</option>
                        <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }}</option>
                    </select>
                    <p v-if="errors.training_module_id" class="text-xs text-red-600 mt-1">{{ errors.training_module_id }}</p>
                </div>
                <div>
                    <label class="text-sm t-muted">Judul Quiz</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="text-sm t-muted">Passing Score (%)</label>
                    <input v-model.number="form.passing_score" type="number" min="1" max="100" required class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Quiz</th>
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Jumlah Soal</th>
                        <th class="px-6 py-3 font-medium">Passing</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="quiz in quizzes" :key="quiz.id" class="border-b border-gray-50">
                        <td class="px-6 py-3">
                            <Link :href="route('platform.quizzes.show', quiz.id)" class="text-indigo-600 font-medium hover:underline">
                                {{ quiz.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ quiz.module?.title }}</td>
                        <td class="px-6 py-3 t-muted">{{ quiz.questions?.length ?? 0 }}</td>
                        <td class="px-6 py-3 t-muted">{{ quiz.passing_score }}%</td>
                    </tr>
                    <tr v-if="quizzes.length === 0">
                        <td colspan="4" class="px-6 py-8 text-center t-muted">Belum ada quiz.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>