<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ notifications: Array });

const processing = ref(null);
const markAll = () => {
    if (processing.value) return;
    processing.value = 'all';
    router.post(route('notifications.readAll'), {}, { onFinish: () => (processing.value = null) });
};
const markOne = (id) => {
    if (processing.value) return;
    processing.value = id;
    router.patch(route('notifications.read', id), {}, { preserveScroll: true, onFinish: () => (processing.value = null) });
};
</script>

<template>
    <Head title="Notifikasi" />

    <AppLayout title="Notifikasi">
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm t-muted">Pemberitahuan penugasan training dan undangan TTX.</p>
            <BaseButton :loading="processing === 'all'" :disabled="processing !== null || notifications.length === 0" @click="markAll">{{ processing === 'all' ? 'Memproses...' : 'Tandai Semua Dibaca' }}</BaseButton>
        </div>

        <div class="card overflow-hidden">
            <div v-for="n in notifications" :key="n.id"
                class="flex min-h-11 cursor-pointer items-start justify-between gap-4 border-b px-4 py-4 b-line transition-colors hover:bg-app focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset sm:px-6"
                :class="n.read_at ? 'opacity-60' : ''"
                role="button"
                tabindex="0"
                @click="markOne(n.id)"
                @keydown.enter.prevent="markOne(n.id)"
                @keydown.space.prevent="markOne(n.id)">
                <div>
                    <div class="text-sm t-ink">{{ n.data.message }}</div>
                    <div class="text-xs t-muted mt-1">{{ new Date(n.created_at).toLocaleString('id-ID') }}</div>
                </div>
                <span v-if="!n.read_at" class="mt-1 h-2 w-2 shrink-0 rounded-full" style="background: var(--brand)" aria-label="Belum dibaca"></span>
            </div>
            <EmptyState v-if="notifications.length === 0" title="Tidak ada notifikasi" message="Pemberitahuan baru akan muncul di halaman ini." />
        </div>
    </AppLayout>
</template>
