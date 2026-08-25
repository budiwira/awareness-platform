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
            <p class="text-sm text-gray-500">
                Buat quiz per modul training. Kunci jawaban tidak pernah dikirim ke browser user.
            </p>
            <button
                @click="showForm = !showForm"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500"
            >
                + Buat Quiz
            </button>
        </div>

        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="text-sm text-gray-600">Modul</label>
                    <select v-model="form.training_module_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="" disabled>-- Pilih Modul --</option>
                        <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }}</option>
                    </select>
                    <p v-if="errors.training_module_id" class="text-xs text-red-600 mt-1">{{ errors.training_module_id }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Judul Quiz</label>
                    <input v-model="form.title" type="text" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Passing Score (%)</label>
                    <input v-model.number="form.passing_score" type="number" min="1" max="100" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
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
                        <td class="px-6 py-3 text-gray-500">{{ quiz.module?.title }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ quiz.questions?.length ?? 0 }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ quiz.passing_score }}%</td>
                    </tr>
                    <tr v-if="quizzes.length === 0">
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Belum ada quiz.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>