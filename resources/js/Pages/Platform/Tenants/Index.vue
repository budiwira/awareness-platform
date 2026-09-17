<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({ tenants: { type: Array, default: () => [] }, packages: { type: Array, default: () => [] } });
const search = ref('');
const status = ref('');
const packageName = ref('');
const refreshing = ref(false);
const feedback = ref('');
const failure = ref('');
const showCreate = ref(false);
const selectedTenant = ref(null);
const createForm = useForm({ name: '' });
const planForm = useForm({ tenant_id: '', package_id: '' });
let returnFocus;
const packageOptions = computed(() => [...new Set(props.tenants.map(t => t.current_package).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'id')));
const filtered = computed(() => props.tenants.filter(tenant =>
    (!search.value.trim() || `${tenant.name} ${tenant.slug}`.toLocaleLowerCase('id').includes(search.value.trim().toLocaleLowerCase('id')))
    && (!status.value || (status.value === 'active' ? tenant.status === 'active' : tenant.status !== 'active'))
    && (!packageName.value || (packageName.value === '__none' ? !tenant.current_package : tenant.current_package === packageName.value))
));
const hasFilters = computed(() => Boolean(search.value || status.value || packageName.value));
const activeCount = computed(() => props.tenants.filter(t => t.status === 'active').length);
// Match the existing list selection; this is recorded subscription context, not entitlement.
const subscription = tenant => [...(tenant.subscriptions ?? [])].filter(s => s.status === 'active')
    .sort((a, b) => (b.started_at ?? '').localeCompare(a.started_at ?? ''))[0];
const formatDate = value => value ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(value)) : 'Belum ditentukan';
const period = tenant => {
    const current = subscription(tenant);
    return current ? `${formatDate(current.started_at)} – ${current.ends_at ? formatDate(current.ends_at) : 'Tanpa tanggal akhir'}` : 'Belum ada langganan berstatus aktif';
};
const resetFilters = () => { search.value = ''; status.value = ''; packageName.value = ''; };
const restoreFocus = () => nextTick(() => returnFocus?.focus());
const keepDialogFocus = event => {
    const controls = [...event.currentTarget.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), a[href]')]
        .filter(element => element.getClientRects().length);
    const first = controls[0];
    const last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first?.focus();
    }
};
const openCreate = async event => {
    returnFocus = event.currentTarget;
    createForm.reset(); createForm.clearErrors(); failure.value = ''; showCreate.value = true;
    await nextTick(); document.getElementById('tenant-name')?.focus();
};
const closeCreate = () => { if (!createForm.processing) { showCreate.value = false; restoreFocus(); } };
const openPlan = async (tenant, event) => {
    returnFocus = event.currentTarget;
    planForm.reset(); planForm.clearErrors(); failure.value = '';
    planForm.tenant_id = tenant.id; selectedTenant.value = tenant;
    await nextTick(); document.getElementById('tenant-package')?.focus();
};
const closePlan = () => { if (!planForm.processing) { selectedTenant.value = null; restoreFocus(); } };
const submitCreate = () => {
    if (createForm.processing) return;
    failure.value = '';
    createForm.post(route('platform.tenants.store'), { preserveScroll: true, onSuccess: () => {
        showCreate.value = false; feedback.value = 'Organisasi berhasil ditambahkan.'; createForm.reset(); restoreFocus();
    } });
};
const submitPlan = () => {
    if (planForm.processing || !planForm.package_id) return;
    failure.value = '';
    planForm.post(route('platform.tenants.set-package'), { preserveScroll: true, onSuccess: () => {
        selectedTenant.value = null; feedback.value = 'Paket organisasi berhasil diperbarui.'; restoreFocus();
    } });
};
const refresh = () => {
    failure.value = ''; refreshing.value = true;
    router.reload({ only: ['tenants', 'packages'], onFinish: () => { refreshing.value = false; } });
};
const stopException = router.on('exception', event => {
    if (!refreshing.value && !createForm.processing && !planForm.processing) return;
    event.preventDefault();
    failure.value = 'Koneksi terputus. Muat ulang data untuk memeriksa hasil sebelum mencoba lagi.';
    refreshing.value = false;
});
onBeforeUnmount(stopException);
</script>

<template>
    <Head title="Organisasi" />
    <AppLayout title="Organisasi">
        <div class="tenant-page fade-in space-y-6">
            <section class="card p-5 sm:p-6 tenant-hero" aria-labelledby="tenant-heading">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider t-muted">Manajemen tenant</p>
                        <h2 id="tenant-heading" class="font-display text-2xl t-ink mt-2">Organisasi pelanggan</h2>
                        <p class="text-sm t-muted mt-2">Pantau status, tinjau paket, dan kelola dukungan akses pengguna.</p>
                    </div>
                    <BaseButton @click="openCreate">Tambah organisasi</BaseButton>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-6 pt-5 border-t b-line">
                    <div><dt class="text-sm t-muted">Total organisasi</dt><dd class="text-2xl font-display t-ink">{{ tenants.length }}</dd></div>
                    <div><dt class="text-sm t-muted">Organisasi aktif</dt><dd class="text-2xl font-display t-ink">{{ activeCount }}</dd></div>
                    <div><dt class="text-sm t-muted">Belum ada paket</dt><dd class="text-2xl font-display t-ink">{{ tenants.filter(t => !t.current_package).length }}</dd></div>
                </dl>
            </section>
            <BaseAlert v-if="feedback" variant="success">{{ feedback }}</BaseAlert>
            <BaseAlert v-if="failure && !showCreate && !selectedTenant" variant="danger">{{ failure }}</BaseAlert>
            <section aria-label="Daftar organisasi" class="space-y-4" :aria-busy="refreshing">
                <div class="card p-4 sm:p-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <BaseInput id="tenant-search" v-model="search" type="search" label="Cari organisasi" placeholder="Nama atau slug organisasi" />
                        <BaseSelect id="tenant-status" v-model="status" label="Status organisasi"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Tidak aktif</option></BaseSelect>
                        <BaseSelect id="tenant-package-filter" v-model="packageName" label="Paket tercatat"><option value="">Semua paket</option><option value="__none">Belum ada paket</option><option v-for="name in packageOptions" :key="name" :value="name">{{ name }}</option></BaseSelect>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm t-muted" role="status">{{ filtered.length }} dari {{ tenants.length }} organisasi</p>
                        <div class="flex flex-wrap gap-2"><BaseButton v-if="hasFilters" variant="ghost" @click="resetFilters">Reset filter</BaseButton><BaseButton variant="secondary" :loading="refreshing" @click="refresh">{{ refreshing ? 'Memuat…' : 'Muat ulang' }}</BaseButton></div>
                    </div>
                </div>
                <p class="text-xs t-muted">Paket tercatat berasal dari langganan berstatus aktif. Periode ditampilkan untuk konteks; ketersediaan akses tetap mengikuti ketentuan langganan.</p>
                <div v-if="refreshing" class="card p-5 space-y-4" role="status"><span class="sr-only">Memuat daftar organisasi</span><div v-for="n in 3" :key="n" class="skeleton h-16" /></div>
                <EmptyState v-else-if="!tenants.length" class="card" title="Belum ada organisasi" message="Tambahkan organisasi pertama untuk mulai mengelola pelanggan." />
                <EmptyState v-else-if="!filtered.length" class="card" title="Organisasi tidak ditemukan" message="Coba nama lain atau reset filter untuk melihat semua organisasi." />
                <BaseTableContainer v-else class="tenant-table">
                    <table class="w-full text-sm" aria-label="Organisasi pelanggan">
                        <thead><tr class="text-left border-b b-line"><th scope="col">Organisasi</th><th scope="col">Status</th><th scope="col">Paket dan periode</th><th scope="col" class="text-right">Pengguna</th><th scope="col">Tindakan</th></tr></thead>
                        <tbody><tr v-for="tenant in filtered" :key="tenant.id" class="border-b b-line" data-testid="tenant-row">
                            <td class="tenant-identity"><p class="font-semibold t-ink">{{ tenant.name }}</p><p class="text-xs t-muted mt-1">{{ tenant.slug }}</p></td>
                            <td data-label="Status"><BaseBadge :variant="tenant.status === 'active' ? 'success' : 'neutral'">{{ tenant.status === 'active' ? 'Aktif' : 'Tidak aktif' }}</BaseBadge></td>
                            <td data-label="Paket dan periode"><div><p class="font-medium t-ink">{{ tenant.current_package || 'Belum ada paket' }}</p><p class="text-xs t-muted mt-1">{{ period(tenant) }}</p></div></td>
                            <td data-label="Pengguna" class="text-right tabular-nums t-ink">{{ tenant.users_count }}</td>
                            <td data-label="Tindakan"><div class="flex flex-wrap gap-2 tenant-actions"><Link :href="route('platform.tenants.user-access.show', tenant.id)" class="btn btn-secondary tenant-link" :aria-label="`Kelola akses ${tenant.name}`">Kelola akses</Link><BaseButton variant="secondary" size="sm" :aria-label="`Atur paket ${tenant.name}`" @click="openPlan(tenant, $event)">Atur paket</BaseButton></div></td>
                        </tr></tbody>
                    </table>
                </BaseTableContainer>
            </section>
        </div>
        <Modal :show="showCreate" max-width="lg" :closeable="!createForm.processing" aria-labelledby="create-title" class="tenant-dialog" @close="closeCreate" @keydown.tab="keepDialogFocus">
            <form class="tenant-page p-5 sm:p-6 space-y-5" @submit.prevent="submitCreate">
                <div><h2 id="create-title" class="font-display text-xl t-ink">Tambah organisasi</h2><p class="text-sm t-muted mt-2">Organisasi baru dibuat dengan status aktif.</p></div>
                <BaseAlert v-if="failure" variant="danger">{{ failure }}</BaseAlert>
                <BaseInput id="tenant-name" v-model="createForm.name" label="Nama organisasi" required :disabled="createForm.processing" :error="createForm.errors.name" hint="Maksimal 120 karakter. Slug dibuat otomatis." />
                <div class="flex flex-wrap justify-end gap-3"><BaseButton variant="secondary" :disabled="createForm.processing" @click="closeCreate">Batal</BaseButton><BaseButton type="submit" :loading="createForm.processing">{{ createForm.processing ? 'Menyimpan…' : 'Simpan organisasi' }}</BaseButton></div>
            </form>
        </Modal>
        <Modal :show="Boolean(selectedTenant)" max-width="lg" :closeable="!planForm.processing" aria-labelledby="plan-title" class="tenant-dialog" @close="closePlan" @keydown.tab="keepDialogFocus">
            <form v-if="selectedTenant" class="tenant-page p-5 sm:p-6 space-y-5" @submit.prevent="submitPlan">
                <div><h2 id="plan-title" class="font-display text-xl t-ink">Atur paket organisasi</h2><p class="font-semibold t-ink mt-2 break-words">{{ selectedTenant.name }}</p><p class="text-sm t-muted mt-1">Paket tercatat: {{ selectedTenant.current_package || 'Belum ada paket' }} · {{ selectedTenant.users_count }} pengguna</p></div>
                <BaseAlert variant="warning" title="Perubahan berlaku segera">Menyimpan akan membatalkan langganan berstatus aktif sebelumnya dan mengaktifkan paket pilihan. Admin organisasi akan menerima notifikasi.</BaseAlert>
                <BaseAlert v-if="failure || planForm.errors.tenant_id" variant="danger">{{ failure || planForm.errors.tenant_id }}</BaseAlert>
                <BaseSelect id="tenant-package" v-model="planForm.package_id" label="Paket pengganti" required :disabled="planForm.processing || !packages.length" :error="planForm.errors.package_id"><option value="" disabled>Pilih paket</option><option v-for="plan in packages" :key="plan.id" :value="plan.id">{{ plan.name }} ({{ plan.max_users === null ? 'Tanpa batas pengguna' : `Maks. ${plan.max_users} pengguna` }})</option></BaseSelect>
                <BaseAlert v-if="!packages.length" variant="info">Belum ada paket aktif yang dapat dipilih.</BaseAlert>
                <div class="flex flex-wrap justify-end gap-3"><BaseButton variant="secondary" :disabled="planForm.processing" @click="closePlan">Batal</BaseButton><BaseButton type="submit" :loading="planForm.processing" :disabled="!planForm.package_id || !packages.length">{{ planForm.processing ? 'Menyimpan…' : 'Terapkan paket' }}</BaseButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.tenant-page { min-width: 0; overflow-wrap: anywhere; }
.tenant-page.fade-in { animation-duration: 200ms; }
.tenant-hero { box-shadow: var(--shadow-sm); }
.tenant-page :deep(input), .tenant-page :deep(select) { min-width: 0; width: 100%; }
.tenant-page :deep(input:hover), .tenant-page :deep(select:hover) { border-color: var(--brand); }
.tenant-page :deep(input:active), .tenant-page :deep(select:active) { border-color: var(--brand-strong); }
.tenant-page :deep(input:focus-visible), .tenant-page :deep(select:focus-visible), .tenant-link:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.tenant-link { min-height: 44px; }
.tenant-table th, .tenant-table td { padding: 1rem; vertical-align: top; }
.tenant-table :deep(table) { table-layout: fixed; min-width: 0; }
.tenant-table th:nth-child(1) { width: 23%; }
.tenant-table th:nth-child(2) { width: 13%; }
.tenant-table th:nth-child(3) { width: 27%; }
.tenant-table th:nth-child(4) { width: 11%; }
.tenant-table th:nth-child(5) { width: 26%; }
.tenant-dialog :deep(.transition-all) { transition-duration: 200ms; }
@media (max-width: 1199px) {
    .tenant-table :deep(.base-table-scroll) { overflow: visible; }
    .tenant-table thead { display: none; }
    .tenant-table table, .tenant-table tbody { display: block; }
    .tenant-table tr { display: grid; grid-template-columns: 1fr 1fr; padding: 1rem; gap: 1rem; }
    .tenant-table td { display: flex; flex-direction: column; gap: .5rem; padding: 0; min-width: 0; text-align: left; }
    .tenant-table td::before { content: attr(data-label); color: var(--muted); font-size: .75rem; }
    .tenant-table .tenant-identity { grid-column: 1 / -1; display: block; }
    .tenant-table .tenant-identity::before { display: none; }
    .tenant-table td:last-child { grid-column: 1 / -1; }
    .tenant-table td:nth-child(4) { text-align: right; }
    .tenant-table td:nth-child(3) { grid-column: 1 / -1; grid-row: 3; }
    .tenant-table td:nth-child(4) { grid-column: 2; grid-row: 2; }
    .tenant-table :deep(.base-badge) { align-self: flex-start; }
}
</style>
