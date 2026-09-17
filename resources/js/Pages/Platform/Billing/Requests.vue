<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ requests: { type: Array, default: () => [] } });

const errors = computed(() => usePage().props.errors ?? {});
const processing = ref(null);
const selectedRequest = ref(null);
const selectedAction = ref(null);

const openDecision = (request, action) => {
    if (processing.value !== null) return;
    selectedRequest.value = request;
    selectedAction.value = action;
};

const resolveRequest = () => {
    if (!selectedRequest.value || !selectedAction.value || processing.value !== null) return;
    const requestId = selectedRequest.value.id;
    processing.value = requestId;
    router.post(route(`platform.billing.${selectedAction.value}`), { request_id: requestId }, {
        onFinish: () => {
            processing.value = null;
            selectedRequest.value = null;
            selectedAction.value = null;
        },
    });
};

const statusMeta = (status) => ({
    pending: { label: 'Menunggu', variant: 'warning' },
    approved: { label: 'Disetujui', variant: 'success' },
    rejected: { label: 'Ditolak', variant: 'danger' },
}[status] ?? { label: status, variant: 'neutral' });

const actionLabel = computed(() => selectedAction.value === 'approve' ? 'Setujui' : 'Tolak');
const pendingCount = computed(() => props.requests.filter(request => request.status === 'pending').length);
const resolvedCount = computed(() => props.requests.length - pendingCount.value);
</script>

<template>
    <Head title="Billing Requests" />

    <AppLayout title="Billing Requests">
        <div class="fade-in space-y-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm t-muted">Tinjau permintaan perubahan paket dari tenant sesuai kebijakan bisnis.</p>
                    <p class="mt-1 text-xs t-muted">Antrean diprioritaskan dari permintaan yang belum diproses.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:min-w-[240px]">
                    <div class="card px-3 py-3">
                        <p class="text-xs t-muted">Menunggu</p>
                        <p class="font-display text-xl t-ink">{{ pendingCount }}</p>
                    </div>
                    <div class="card px-3 py-3">
                        <p class="text-xs t-muted">Selesai</p>
                        <p class="font-display text-xl t-ink">{{ resolvedCount }}</p>
                    </div>
                </div>
            </header>

            <BaseAlert v-if="errors.request_id" variant="danger" title="Permintaan tidak dapat diproses">
                {{ errors.request_id }}
            </BaseAlert>

            <section class="card overflow-hidden" aria-labelledby="billing-queue-heading">
                <div class="border-b b-line px-4 py-4 sm:px-6">
                    <h2 id="billing-queue-heading" class="font-display text-lg t-ink">Antrean permintaan</h2>
                    <p class="mt-1 text-sm t-muted">{{ requests.length }} permintaan tercatat</p>
                </div>
                <BaseTableContainer v-if="requests.length">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b b-line text-left text-xs uppercase tracking-wide t-muted">
                                <th class="px-4 py-3 font-semibold sm:px-6">Tenant</th>
                                <th class="px-4 py-3 font-semibold sm:px-6">Paket diminta</th>
                                <th class="px-4 py-3 font-semibold sm:px-6">Catatan</th>
                                <th class="px-4 py-3 font-semibold sm:px-6">Pemohon</th>
                                <th class="px-4 py-3 font-semibold sm:px-6">Tanggal</th>
                                <th class="px-4 py-3 font-semibold sm:px-6">Status</th>
                                <th class="px-4 py-3 text-right font-semibold sm:px-6">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in requests" :key="req.id" class="border-b b-line last:border-0">
                                <td class="px-4 py-4 sm:px-6">
                                    <span class="block max-w-[180px] truncate font-semibold t-ink" :title="req.tenant_name">{{ req.tenant_name }}</span>
                                    <span class="text-xs t-muted">Tenant #{{ req.tenant_id }}</span>
                                </td>
                                <td class="px-4 py-4 sm:px-6">
                                    <span class="block max-w-[180px] truncate font-semibold t-ink" :title="req.plan_name">{{ req.plan_name }}</span>
                                    <span class="text-xs t-muted">{{ req.plan_slug }}</span>
                                </td>
                                <td class="max-w-[220px] truncate px-4 py-4 t-muted sm:px-6" :title="req.note || 'Tidak ada catatan'">{{ req.note || 'Tidak ada catatan' }}</td>
                                <td class="px-4 py-4 t-muted sm:px-6">{{ req.requested_by }}</td>
                                <td class="whitespace-nowrap px-4 py-4 t-muted sm:px-6">{{ req.requested_at }}</td>
                                <td class="px-4 py-4 sm:px-6">
                                    <BaseBadge :variant="statusMeta(req.status).variant">{{ statusMeta(req.status).label }}</BaseBadge>
                                </td>
                                <td class="px-4 py-4 text-right sm:px-6">
                                    <div v-if="req.status === 'pending'" class="flex flex-col justify-end gap-2 sm:flex-row">
                                        <BaseButton size="sm" :disabled="processing !== null" @click="openDecision(req, 'approve')">Setujui</BaseButton>
                                        <BaseButton size="sm" variant="danger" :disabled="processing !== null" @click="openDecision(req, 'reject')">Tolak</BaseButton>
                                    </div>
                                    <div v-else class="text-xs t-muted">
                                        <span class="block">{{ req.resolved_by || '—' }}</span>
                                        <span>{{ req.resolved_at || '—' }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </BaseTableContainer>
                <EmptyState v-else title="Antrean kosong" message="Belum ada permintaan billing yang perlu ditinjau." />
            </section>
        </div>

        <Modal :show="selectedRequest !== null" max-width="md" aria-label="Konfirmasi keputusan billing" @close="selectedRequest = null; selectedAction = null">
            <div class="p-6">
                <h2 class="font-display text-lg t-ink">{{ actionLabel }} permintaan paket?</h2>
                <p class="mt-2 text-sm t-muted">
                    {{ actionLabel }} permintaan dari <strong class="t-ink">{{ selectedRequest?.tenant_name }}</strong>
                    untuk paket <strong class="t-ink">{{ selectedRequest?.plan_name }}</strong>?
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <BaseButton variant="secondary" :disabled="processing !== null" @click="selectedRequest = null; selectedAction = null">Batal</BaseButton>
                    <BaseButton :variant="selectedAction === 'approve' ? 'primary' : 'danger'" :loading="processing !== null" :disabled="processing !== null" @click="resolveRequest">
                        {{ processing !== null ? 'Memproses...' : actionLabel }}
                    </BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
