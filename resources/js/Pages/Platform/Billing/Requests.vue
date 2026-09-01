<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ requests: Array });

const errors = computed(() => usePage().props.errors ?? {});
const processing = ref(null);

const approve = (requestId) => {
    if (!confirm('Approve permintaan plan ini?')) return;
    processing.value = requestId;
    router.post(route('platform.billing.approve'), { request_id: requestId }, {
        onFinish: () => processing.value = null,
    });
};

const reject = (requestId) => {
    if (!confirm('Reject permintaan plan ini?')) return;
    processing.value = requestId;
    router.post(route('platform.billing.reject'), { request_id: requestId }, {
        onFinish: () => processing.value = null,
    });
};

const statusBadge = (status) => {
    if (status === 'pending') return 'bg-yellow-100 text-yellow-700';
    if (status === 'approved') return 'bg-emerald-100 badge-ok';
    return 'bg-red-100 text-red-700';
};
</script>

<template>
    <Head title="Billing Requests" />

    <AppLayout title="Billing Requests">
        <div class="mb-6">
            <p class="text-sm t-muted">
                Permintaan perubahan plan dari tenant. Approve atau reject berdasarkan kebijakan bisnis.
            </p>
        </div>

        <div v-if="errors.request_id" class="mb-4 p-4 bg-red-50 text-red-700 rounded-xl text-sm">
            {{ errors.request_id }}
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Tenant</th>
                        <th class="px-6 py-3 font-medium">Plan Diminta</th>
                        <th class="px-6 py-3 font-medium">Note</th>
                        <th class="px-6 py-3 font-medium">Diminta Oleh</th>
                        <th class="px-6 py-3 font-medium">Tanggal</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="requests.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p>Tidak ada permintaan billing</p>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="req in requests" :key="req.id" class="border-b border-gray-50 hover:bg-gray-25 transition-all">
                        <td class="px-6 py-3 font-medium t-ink">{{ req.tenant_name }}</td>
                        <td class="px-6 py-3">
                            <span class="font-medium text-indigo-600">{{ req.plan_name }}</span>
                        </td>
                        <td class="px-6 py-3 t-muted max-w-xs truncate">{{ req.note || '-' }}</td>
                        <td class="px-6 py-3 t-muted">{{ req.requested_by }}</td>
                        <td class="px-6 py-3 t-muted">{{ req.requested_at }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(req.status)">
                                {{ req.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <div v-if="req.status === 'pending'" class="flex gap-2">
                                <button
                                    @click="approve(req.id)"
                                    :disabled="processing === req.id"
                                    class="btn btn-primary text-xs py-1 px-3"
                                >
                                    Approve
                                </button>
                                <button
                                    @click="reject(req.id)"
                                    :disabled="processing === req.id"
                                    class="btn btn-danger text-xs py-1 px-3"
                                >
                                    Reject
                                </button>
                            </div>
                            <div v-else class="text-xs t-muted">
                                {{ req.resolved_by }} - {{ req.resolved_at }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
