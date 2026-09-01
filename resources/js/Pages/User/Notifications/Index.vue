<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ notifications: Array });

const markAll = () => router.post(route('notifications.readAll'));
const markOne = (id) => router.patch(route('notifications.read', id));
</script>

<template>
    <Head title="Notifikasi" />

    <AppLayout title="Notifikasi">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">Pemberitahuan penugasan training dan undangan TTX.</p>
            <button @click="markAll" class="btn btn-primary">
                Tandai Semua Dibaca
            </button>
        </div>

        <div class="card overflow-hidden">
            <div v-for="n in notifications" :key="n.id"
                class="flex items-start justify-between px-6 py-4 border-b border-gray-50 cursor-pointer hover:bg-app"
                :class="n.read_at ? 'opacity-60' : ''"
                @click="markOne(n.id)">
                <div>
                    <div class="text-sm t-ink">{{ n.data.message }}</div>
                    <div class="text-xs text-gray-400 mt-1">{{ new Date(n.created_at).toLocaleString('id-ID') }}</div>
                </div>
                <span v-if="!n.read_at" class="mt-1 w-2 h-2 rounded-full bg-indigo-500 shrink-0"></span>
            </div>
            <div v-if="notifications.length === 0" class="px-6 py-8 text-center t-muted text-sm">
                Tidak ada notifikasi.
            </div>
        </div>
    </AppLayout>
</template>