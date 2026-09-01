<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ participation_id: Number, case_title: String, scenes: Array });

const answers = ref({});
const errors = ref({});

const submit = () => {
    router.post(route('user.cases.submit', props.participation_id), { answers: answers.value }, {
        onError: (e) => (errors.value = e),
    });
};
</script>

<template>
    <Head :title="'Case: ' + case_title" />

    <AppLayout :title="'Case: ' + case_title">
        <div class="mb-6">
            <Link :href="route('user.cases.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Case
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <p class="text-sm t-muted">
                Pilih keputusan yang menurut Anda paling tepat untuk setiap situasi.
            </p>
            <p v-if="errors.answers" class="text-sm text-red-600 mt-2">{{ errors.answers }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div v-for="(scene, si) in scenes" :key="scene.id" class="card p-6">
                <div class="text-xs uppercase tracking-wide text-gray-400 mb-2">Scene {{ si + 1 }}</div>
                <div class="font-medium t-ink mb-4">{{ scene.situation }}</div>
                <div class="space-y-2">
                    <label
                        v-for="(opt, oi) in scene.options"
                        :key="oi"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-lg border b-line cursor-pointer hover:bg-app text-sm"
                    >
                        <input
                            type="radio"
                            :name="'scene' + scene.id"
                            :value="oi"
                            v-model="answers[scene.id]"
                            class="text-indigo-600"
                        />
                        {{ String.fromCharCode(65 + oi) }}. {{ opt.text }}
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button class="btn btn-primary">
                    Kumpulkan Keputusan
                </button>
            </div>
        </form>
    </AppLayout>
</template>