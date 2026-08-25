<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    assignment: Object,
});

const isCompleted = ref(props.assignment.status === 'completed');

const statusBadgeClass = computed(() => {
    if (isCompleted.value) return 'bg-emerald-100 text-emerald-700';
    if (props.assignment.status === 'in_progress') return 'bg-blue-100 text-blue-700';
    return 'bg-yellow-100 text-yellow-700';
});

const statusLabel = computed(() => {
    if (isCompleted.value) return 'Selesai';
    if (props.assignment.status === 'in_progress') return 'Sedang Dikerjakan';
    return 'Ditugaskan';
});

const markComplete = () => {
    if (isCompleted.value) return;

    if (confirm('Tandai modul ini sebagai selesai?')) {
        router.patch(route('user.training.complete', props.assignment.id), {
            preserveScroll: true,
            onSuccess: () => {
                isCompleted.value = true;
            },
        });
    }
};
</script>

<template>
    <Head :title="assignment.module.title" />

    <AppLayout :title="assignment.module.title">
        <div class="mb-6">
            <Link :href="route('user.training.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Training
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ assignment.module.title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Durasi: {{ assignment.module.duration_minutes }} menit</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium" :class="statusBadgeClass">
                    {{ statusLabel }}
                </span>
            </div>

            <div v-if="assignment.module.description" class="mb-6 p-4 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-700">{{ assignment.module.description }}</p>
            </div>

            <div class="prose max-w-none mb-8" v-html="assignment.module.content"></div>

            <div class="flex justify-end">
                <button
                    v-if="!isCompleted"
                    @click="markComplete"
                    class="px-6 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500 transition"
                >
                    Tandai Sebagai Selesai
                </button>
                <span
                    v-else
                    class="px-6 py-2 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-medium flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Modul Selesai
                </span>
            </div>
        </div>
    </AppLayout>
</template>