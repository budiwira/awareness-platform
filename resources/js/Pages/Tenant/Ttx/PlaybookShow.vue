<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ playbook: Object });
</script>

<template>
    <Head :title="`Playbook: ${playbook.title}`" />

    <AppLayout :title="playbook.title">
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
                    <h1 class="font-display text-2xl font-bold t-ink">{{ playbook.title }}</h1>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="badge bg-indigo-50 text-indigo-700 border border-indigo-200">Playbook</span>
                        <span v-if="playbook.is_active" class="badge badge-ok badge-ok border border-emerald-200">Aktif</span>
                    </div>
                </div>
            </div>

            <div v-if="playbook.description" class="mb-6">
                <h2 class="text-sm font-semibold t-ink mb-2">Deskripsi</h2>
                <p class="t-muted leading-relaxed">{{ playbook.description }}</p>
            </div>

            <div v-if="playbook.content" class="mb-6">
                <h2 class="text-sm font-semibold t-ink mb-3">Prosedur</h2>
                <div class="bg-app rounded-lg p-6 border b-line">
                    <pre class="text-sm t-ink whitespace-pre-wrap font-mono leading-relaxed">{{ playbook.content }}</pre>
                </div>
            </div>

            <div class="pt-6 border-t b-line">
                <h2 class="text-sm font-semibold t-ink mb-3">Metadata</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="t-muted">ID</dt>
                        <dd class="font-mono t-ink">{{ playbook.id }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Status</dt>
                        <dd class="t-ink">{{ playbook.is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Dibuat</dt>
                        <dd class="t-ink">{{ new Date(playbook.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</dd>
                    </div>
                    <div>
                        <dt class="t-muted">Diperbarui</dt>
                        <dd class="t-ink">{{ new Date(playbook.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>
