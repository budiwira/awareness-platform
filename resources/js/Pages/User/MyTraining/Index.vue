<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { BaseBadge } from '@/Components';

defineProps({ assignments: Array });

const statusLabel = (status) => ({
    assigned: 'Ditugaskan',
    in_progress: 'Sedang dikerjakan',
    completed: 'Selesai',
}[status] || 'Ditugaskan');

const statusVariant = (status) => ({
    assigned: 'warning',
    in_progress: 'info',
    completed: 'success',
}[status] || 'warning');

const ctaLabel = (status) => status === 'completed' ? 'Buka modul' : (status === 'in_progress' ? 'Lanjutkan' : 'Mulai modul');
</script>

<template>
    <Head title="My Training" />

    <AppLayout title="Training Saya">
        <div class="mb-6">
            <p class="text-sm t-muted max-w-2xl">
                Selesaikan modul yang ditugaskan untuk memperkuat pemahaman dan kebiasaan keamanan Anda.
            </p>
        </div>

        <div v-if="assignments.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="assignment in assignments" :key="assignment.id" class="card p-6 flex flex-col gap-5 fade-in">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide t-muted mb-2">Modul training</p>
                        <h2 class="font-display text-lg font-semibold t-ink">{{ assignment.module.title }}</h2>
                        <p v-if="assignment.module.description" class="text-sm t-muted mt-2 line-clamp-2">
                            {{ assignment.module.description }}
                        </p>
                    </div>
                    <BaseBadge class="shrink-0" :variant="statusVariant(assignment.status)">
                        {{ statusLabel(assignment.status) }}
                    </BaseBadge>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm t-muted">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        {{ assignment.module.duration_minutes }} menit
                    </span>
                    <span v-if="assignment.score !== null" class="font-medium t-ink">Skor {{ assignment.score }}</span>
                </div>

                <div class="mt-auto pt-1">
                    <Link
                        :href="route('user.training.show', assignment.id)"
                        class="btn btn-primary w-full justify-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    >
                        {{ ctaLabel(assignment.status) }}
                        <svg class="ml-2 h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </Link>
                </div>
            </article>
        </div>

        <div v-else class="card fade-in"><EmptyState title="Belum ada modul training" message="Modul yang ditugaskan kepada Anda akan muncul di sini." /></div>
    </AppLayout>
</template>
