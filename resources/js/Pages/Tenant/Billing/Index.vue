<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  packages: Array,
  current_plan: Object,
  current_subscription: Object,
  entitlements: Object,
  user_count: Number,
  requests: Array,
});

const errors = computed(() => usePage().props.errors ?? {});
const flash = computed(() => usePage().props.flash ?? {});

const showRequestForm = ref(false);
const requestForm = ref({ package_id: '', note: '' });
const submitting = ref(false);

const submitRequest = () => {
    submitting.value = true;
    router.post(route('tenant.billing.request'), requestForm.value, {
        onFinish: () => {
            submitting.value = false;
            showRequestForm.value = false;
            requestForm.value = { package_id: '', note: '' };
        },
    });
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');

const statusBadge = (status) => {
    const map = {
        pending: 'badge-warn',
        approved: 'chip-brand',
        rejected: 'badge-danger',
    };
    return map[status] || 'bg-surface2 t-ink';
};

const statusLabel = (status) => {
    const map = { pending: 'Menunggu', approved: 'Disetujui', rejected: 'Ditolak' };
    return map[status] || status;
};

const formatDate = (d) => new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <Head title="Billing" />

    <AppLayout title="Billing & Langganan">
        <!-- Flash message -->
        <div v-if="flash.success" class="mb-6 text-sm chip-brand chip-brand  rounded-xl px-4 py-3 fade-in">
            {{ flash.success }}
        </div>

        <p v-if="errors.package_id" class="mb-6 text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            {{ errors.package_id }}
        </p>

        <!-- Kartu Package aktif (READ-ONLY) -->
        <div class="card p-6 mb-6 flex items-center justify-between">
            <div>
                <div class="text-sm t-muted">Package aktif saat ini</div>
                <div class="text-xl font-display font-bold t-ink mt-1">{{ current_plan?.name ?? 'Free' }}</div>
                <div class="text-xs t-muted mt-1">
                    {{ user_count }} / {{ current_plan?.max_users }} users terpakai Â· {{ entitlements?.module_info }}
                </div>
                <div v-if="entitlements?.features?.length" class="flex flex-wrap gap-1 mt-2">
                    <span v-for="f in entitlements.features" :key="f" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium chip-brand chip-brand ">{{ f }}</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-indigo-600">{{ formatPrice(current_plan?.price_monthly ?? 0) }}</div>
                <div v-if="current_subscription" class="text-xs t-muted mt-1">
                    sejak {{ formatDate(current_subscription.started_at) }}
                </div>
            </div>
        </div>
        <!-- Kartu fitur terkunci -->
        <div v-if="packages.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div v-for="f in ['training','reports_export','ttx','case_studies','ctf']" :key="f" class="card p-4 flex flex-col items-center text-center" :class="entitlements?.features?.includes(f) ? 'bg-surface' : 'bg-app border-dashed'">
                <svg v-if="!entitlements?.features?.includes(f)" class="w-6 h-6 mb-2" style="color: var(--warn)" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span class="text-xs font-medium" :class="entitlements?.features?.includes(f) ? 't-ink' : 't-muted'">{{ f }}</span>
                <span v-if="!entitlements?.features?.includes(f)" class="text-xs badge-warn mt-1">Terkunci â€” Ajukan Upgrade</span>
            </div>
        </div>

        <!-- Form ajukan perubahan Package -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-display font-semibold t-ink mb-4">Ajukan Perubahan Package</h2>
            
            <div v-if="!showRequestForm">
                <button @click="showRequestForm = true" class="btn btn-primary">
                    Ajukan Permintaan
                </button>
            </div>

            <form v-else @submit.prevent="submitRequest" class="space-y-4 fade-in">
                <div>
                    <label class="block text-sm font-medium t-ink mb-2">Pilih Package</label>
                    <select v-model="requestForm.package_id" required
                        class="w-full rounded-lg b-line focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150">
                        <option value="">-- Pilih Package --</option>
                        <option v-for="Package in packages" :key="Package.id" :value="Package.id">
                            {{ Package.name }} ({{ formatPrice(Package.price_monthly) }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium t-ink mb-2">Catatan (opsional)</label>
                    <textarea v-model="requestForm.note" rows="3" maxlength="500"
                        class="w-full rounded-lg b-line focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-150"
                        placeholder="Jelaskan alasan perubahan Package..."></textarea>
                    <div class="text-xs t-muted mt-1">{{ requestForm.note.length }} / 500 karakter</div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" :disabled="submitting" class="btn btn-primary">
                        {{ submitting ? 'Mengirim...' : 'Kirim Permintaan' }}
                    </button>
                    <button type="button" @click="showRequestForm = false" class="btn btn-secondary">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- Riwayat request -->
        <div class="card p-6">
            <h2 class="text-lg font-display font-semibold t-ink mb-4">Riwayat Permintaan</h2>

            <div v-if="!requests || requests.length === 0" class="text-center py-8 t-muted">
                <svg class="w-12 h-12 mx-auto mb-3 t-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm">Belum ada permintaan perubahan Package.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase t-muted border-b b-line">
                        <tr>
                            <th class="text-left py-3 px-4">Tanggal</th>
                            <th class="text-left py-3 px-4">Package</th>
                            <th class="text-left py-3 px-4">Catatan</th>
                            <th class="text-left py-3 px-4">Diajukan Oleh</th>
                            <th class="text-left py-3 px-4">Status</th>
                            <th class="text-left py-3 px-4">Diproses Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="req in requests" :key="req.id" 
                            class="hover:bg-app transition-colors duration-150">
                            <td class="py-3 px-4 t-muted">{{ formatDate(req.created_at) }}</td>
                            <td class="py-3 px-4 font-medium t-ink">{{ req.Package?.name }}</td>
                            <td class="py-3 px-4 t-muted">
                                <span v-if="req.note" class="max-w-xs truncate block">{{ req.note }}</span>
                                <span v-else class="t-muted">â€”</span>
                            </td>
                            <td class="py-3 px-4 t-muted">{{ req.requested_by?.name }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium" :class="statusBadge(req.status)">
                                    {{ statusLabel(req.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 t-muted">
                                <span v-if="req.resolved_by">{{ req.resolved_by.name }}</span>
                                <span v-else class="t-muted">â€”</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-xs t-muted mt-6">
            * Model bisnis managed: super admin yang menetapkan Package. Tenant mengajukan permintaan perubahan.
        </p>
    </AppLayout>
</template>
