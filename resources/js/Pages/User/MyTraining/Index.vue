<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ assignments: Array });

const markComplete = (assignment) => {
    if (confirm('Tandai modul ini sebagai selesai?')) {
        router.patch(route('user.training.complete', assignment.id));
    }
};
</script>

<template>
    <Head title="My Training" />

    <AppLayout title="Training Saya">
        <p class="text-sm t-muted mb-6">
            Modul training yang ditugaskan kepada Anda. Klik judul untuk membaca materi.
        </p>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Durasi</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in assignments" :key="assignment.id" class="border-b b-line">
                        <td class="px-6 py-3">
                            <Link
                                :href="route('user.training.show', assignment.id)"
                                class="text-indigo-600 font-medium hover:underline"
                            >
                                {{ assignment.module.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ assignment.module.duration_minutes }} menit</td>
                        <td class="px-6 py-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="{
                                    'bg-yellow-100 text-yellow-700': assignment.status === 'assigned',
                                    'bg-blue-100 text-blue-700': assignment.status === 'in_progress',
                                    'badge-ok': assignment.status === 'completed'
                                }"
                            >
                                {{ assignment.status === 'completed' ? 'Selesai' : (assignment.status === 'in_progress' ? 'Sedang Dikerjakan' : 'Ditugaskan') }}
                            </span>
                            <span v-if="assignment.score !== null" class="ml-2 text-xs t-muted">
                                Skor: {{ assignment.score }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right space-x-2">
                            <Link
                                v-if="assignment.module?.quiz"
                                :href="route('user.training.quiz', assignment.id)"
                                class="btn btn-primary text-xs"
                            >
                                Kerjakan Quiz
                            </Link>
                            <button
                                v-if="assignment.status !== 'completed'"
                                @click="markComplete(assignment)"
                                class="badge-ok text-xs font-medium"
                            >
                                Tandai Selesai
                            </button>
                            <span v-else class="text-xs text-gray-400">✓ Selesai</span>
                        </td>
                    </tr>
                    <tr v-if="assignments.length === 0">
                        <td colspan="4" class="px-6 py-8 text-center t-muted">Belum ada training yang ditugaskan.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>