<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ assignment: Object, questions: Array, passing_score: Number });

const answers = ref({});
const errors = ref({});

const submit = () => {
    router.post(route('user.training.quiz.submit', props.assignment.id), { answers: answers.value }, {
        onError: (e) => (errors.value = e),
    });
};
</script>

<template>
    <Head :title="'Quiz: ' + assignment.module_title" />

    <AppLayout :title="'Quiz: ' + assignment.module_title">
        <div class="mb-6">
            <Link :href="route('user.training.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Training
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <p class="text-sm t-muted">
                Jawab semua pertanyaan. Nilai kelulusan: <span class="font-semibold">{{ passing_score }}%</span>.
            </p>
            <p v-if="errors.answers" class="text-sm text-red-600 mt-2">{{ errors.answers }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div v-for="(q, qi) in questions" :key="q.id" class="card p-6">
                <div class="font-medium t-ink mb-4">{{ qi + 1 }}. {{ q.question }}</div>
                <div class="space-y-2">
                    <label
                        v-for="(opt, oi) in q.options"
                        :key="oi"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-lg border b-line cursor-pointer hover:bg-app text-sm"
                    >
                        <input
                            type="radio"
                            :name="'q' + q.id"
                            :value="oi"
                            v-model="answers[q.id]"
                            class="text-indigo-600"
                        />
                        {{ String.fromCharCode(65 + oi) }}. {{ opt }}
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button class="btn btn-primary">
                    Kumpulkan Jawaban
                </button>
            </div>
        </form>
    </AppLayout>
</template>