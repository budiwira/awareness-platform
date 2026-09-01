<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ runbook: Object });
</script>

<template>
    <Head :title="`Runbook: ${runbook.title}`" />

    <AppLayout :title="runbook.title">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.index')" class="text-sm chip-brand transition-colors inline-flex items-center gap-1" style="--hover-color: var(--brand-strong)" @mouseenter="$event.currentTarget.style.color = 'var(--brand-strong)'" @mouseleave="$event.currentTarget.style.color = ''">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali ke TTX
            </Link>
        </div>

        <div class="card p-8">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="font-display text-2xl font-bold t-ink">{{ runbook.title }}</h1>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="badge badge-ok badge-ok border border-emerald-200">Runbook</span>
                        <span v-if="runbook.is_active" class="badge badge-ok badge-ok border border-emerald-200">Aktif</span>
                    </div>
                </div>
            </div>

            <div v-if="runbook.description" class="mb-6">
                <h2 class="text-sm font-semibold t-ink mb-2">Deskripsi</h2>
                <p class="t-muted leading-relaxed">{{ runbook.description }}</p>
            </div>

            <div v-if="runbook.steps && runbook.steps.length > 0" class="mb-6">
                <h2 class="text-sm font-semibold t-ink mb-3">Langkah-langkah</h2>
                <div class="bg-app rounded-lg p-6 border b-line">
                    <ol class="space-y-3">
                        <li v-for="(step, i) in runbook.steps" :key="i" class="flex gap-3">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full chip-active text-xs font-bold flex items-center justify-center">{{ i + 1 }}</span>
                            <span class="text-sm t-ink pt-0.5">{{ step }}</span>
                        </li>
                    </ol>
                </div>
            </div>

            <div class="pt-6 border-t b-line">
                <h2 class="text-sm font-semibold t-ink mb-3">Metadata</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="t-muted">ID</dt>
                        <dd class="font-mono t-ink">{{ runbook.id }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Status</dt>
                        <dd class="t-ink">{{ runbook.is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Jumlah Langkah</dt>
                        <dd class="t-ink">{{ runbook.steps?.length || 0 }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Dibuat</dt>
                        <dd class="t-ink">{{ new Date(runbook.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Diperbarui</dt>
                        <dd class="t-ink">{{ new Date(runbook.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
