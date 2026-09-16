<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({ participation_id: Number, case_title: String, scenes: Array });
const answers = ref({});
const errors = ref({});
const submitting = ref(false);

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    router.post(route('user.cases.submit', props.participation_id), { answers: answers.value }, {
        onError: (errorBag) => (errors.value = errorBag),
        onFinish: () => (submitting.value = false),
    });
};
</script>

<template>
    <Head :title="'Case: ' + case_title" />
    <AppLayout :title="'Case: ' + case_title">
        <div class="mb-6">
            <Link :href="route('user.cases.index')" class="inline-flex min-h-11 items-center text-sm t-muted transition-colors hover:text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2">← Kembali ke Daftar Case</Link>
        </div>
        <div class="card mb-6 p-5 sm:p-6">
            <p class="text-sm t-muted">Pilih keputusan yang menurut Anda paling tepat untuk setiap situasi.</p>
            <BaseAlert v-if="errors.answers" variant="danger" class="mt-3">{{ errors.answers }}</BaseAlert>
        </div>
        <form class="space-y-6" @submit.prevent="submit">
            <fieldset v-for="(scene, sceneIndex) in scenes" :key="scene.id" class="card p-5 sm:p-6" :disabled="submitting">
                <legend class="sr-only">Scene {{ sceneIndex + 1 }}</legend>
                <div class="mb-2 text-xs uppercase tracking-wide t-muted">Scene {{ sceneIndex + 1 }}</div>
                <div class="mb-4 font-medium t-ink">{{ scene.situation }}</div>
                <div class="space-y-2">
                    <label v-for="(option, optionIndex) in scene.options" :key="optionIndex" class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border px-4 py-2.5 text-sm b-line transition-colors hover:bg-app focus-within:ring-2">
                        <input v-model="answers[scene.id]" type="radio" :name="'scene' + scene.id" :value="optionIndex" class="h-5 w-5 accent-[var(--brand)]" />
                        {{ String.fromCharCode(65 + optionIndex) }}. {{ option.text }}
                    </label>
                </div>
            </fieldset>
            <div class="flex justify-end"><BaseButton type="submit" :loading="submitting">{{ submitting ? 'Mengirim...' : 'Kumpulkan Keputusan' }}</BaseButton></div>
        </form>
    </AppLayout>
</template>
