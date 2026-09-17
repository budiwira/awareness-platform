<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTableContainer from '@/Components/BaseTableContainer.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const searchInput = ref(props.search ?? '');
const tenantSelect = ref('');
const selectedTenantName = ref('');
const showDeleteModal = ref(false);
const userToDelete = ref(null);
const cancelDeleteButton = ref(null);
const deleteTrigger = ref(null);
const listLoading = ref(false);
const deleteProcessing = ref(false);
const requestPending = ref(false);
const failure = ref('');

const tenantOptions = computed(() => {
    const tenants = new Map();
    props.users.forEach(user => {
        if (user.tenant_id && user.tenant_name) tenants.set(user.tenant_id, user.tenant_name);
    });
    if (tenantSelect.value && selectedTenantName.value) {
        tenants.set(tenantSelect.value, selectedTenantName.value);
    }

    return [...tenants.entries()]
        .map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name, 'id'));
});

watch(tenantSelect, id => {
    selectedTenantName.value = props.users.find(user => String(user.tenant_id) === String(id))?.tenant_name ?? '';
});

const filteredUsers = computed(() => props.users.filter(user =>
    !tenantSelect.value || String(user.tenant_id) === String(tenantSelect.value)
));
const hasFilters = computed(() => Boolean(searchInput.value.trim() || tenantSelect.value));

const roleLabel = role => ({
    super_admin: 'Super admin',
    tenant_admin: 'Admin tenant',
    user: 'Pengguna',
}[role] ?? role);

const roleVariant = role => ({
    super_admin: 'brand',
    tenant_admin: 'info',
    user: 'neutral',
}[role] ?? 'neutral');

const handleSearch = () => {
    if (listLoading.value || deleteProcessing.value) return;
    failure.value = '';
    listLoading.value = true;
    requestPending.value = true;
    router.get(route('platform.users.index'), { search: searchInput.value.trim() || null }, {
        preserveState: true,
        replace: true,
        onSuccess: () => { requestPending.value = false; },
        onError: errors => {
            if (!errors || !Object.keys(errors).length) failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.';
            requestPending.value = false;
        },
        onCancel: () => { failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.'; },
        onFinish: () => {
            if (requestPending.value) failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.';
            requestPending.value = false;
            listLoading.value = false;
        },
    });
};

const resetFilters = () => {
    searchInput.value = '';
    tenantSelect.value = '';
    handleSearch();
};

const openDeleteModal = user => {
    failure.value = '';
    deleteTrigger.value = document.activeElement;
    userToDelete.value = user;
    showDeleteModal.value = true;
    nextTick(() => cancelDeleteButton.value?.$el?.focus());
};

const closeDeleteModal = () => {
    if (!deleteProcessing.value) {
        showDeleteModal.value = false;
        userToDelete.value = null;
        nextTick(() => deleteTrigger.value?.focus());
    }
};

const confirmDelete = () => {
    if (!userToDelete.value || deleteProcessing.value) return;
    failure.value = '';
    deleteProcessing.value = true;
    requestPending.value = true;
    router.post(route('platform.users.destroy'), { user_id: userToDelete.value.id }, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
            requestPending.value = false;
        },
        onError: errors => {
            if (!errors || !Object.keys(errors).length) failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.';
            requestPending.value = false;
        },
        onCancel: () => { failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.'; },
        onFinish: () => {
            if (requestPending.value) failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.';
            requestPending.value = false;
            deleteProcessing.value = false;
        },
    });
};

const trapDeleteFocus = event => {
    const buttons = [...event.currentTarget.querySelectorAll('button:not(:disabled)')];
    if (!buttons.length) return;
    const first = buttons[0];
    const last = buttons[buttons.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const stopException = router.on('exception', event => {
    if (!requestPending.value) return;
    event.preventDefault();
    requestPending.value = false;
    listLoading.value = false;
    deleteProcessing.value = false;
    failure.value = 'Koneksi terputus. Muat ulang data sebelum mencoba lagi.';
});

onBeforeUnmount(stopException);
</script>

<template>
    <Head title="Pengguna platform" />

    <AppLayout title="Pengguna platform">
        <div class="platform-users-page fade-in space-y-6">
            <section class="card p-5 sm:p-6 users-hero" aria-labelledby="users-heading">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wider t-muted">Manajemen lintas tenant</p>
                        <h2 id="users-heading" class="font-display text-2xl t-ink mt-2">Pengguna platform</h2>
                        <p class="text-sm t-muted mt-2">Tinjau identitas, peran, tenant, dan status akses pengguna.</p>
                    </div>
                    <div class="users-total text-right">
                        <p class="text-xs t-muted">{{ props.search ? 'Hasil pencarian' : 'Total pengguna' }}</p>
                        <p class="font-display text-2xl t-ink tabular-nums">{{ users.length }}</p>
                    </div>
                </div>
            </section>

            <BaseAlert v-if="errors.user_id && !showDeleteModal" variant="danger">{{ errors.user_id }}</BaseAlert>
            <BaseAlert v-if="failure && !showDeleteModal" variant="danger">{{ failure }}</BaseAlert>

            <section aria-label="Filter pengguna" class="card p-4 sm:p-5 space-y-4" :aria-busy="listLoading">
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto] gap-4 items-end">
                    <BaseInput
                        id="user-search"
                        v-model="searchInput"
                        type="search"
                        label="Cari pengguna"
                        placeholder="Nama atau email"
                        :disabled="listLoading"
                        @keyup.enter="handleSearch"
                    />
                    <BaseSelect id="user-tenant" v-model="tenantSelect" label="Tenant" :disabled="listLoading">
                        <option value="">Semua tenant</option>
                        <option v-for="tenant in tenantOptions" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
                    </BaseSelect>
                    <div class="flex flex-wrap gap-2">
                        <BaseButton :loading="listLoading" @click="handleSearch">{{ listLoading ? 'Memuat…' : 'Cari' }}</BaseButton>
                        <BaseButton v-if="hasFilters" variant="ghost" :disabled="listLoading" @click="resetFilters">Reset</BaseButton>
                    </div>
                </div>
                <p class="text-xs t-muted">Filter tenant berlaku pada hasil pencarian yang dimuat. Pilihan mengikuti hasil dan tenant terpilih.</p>
                <p class="text-sm t-muted" role="status">{{ filteredUsers.length }} dari {{ users.length }} pengguna ditampilkan</p>
            </section>

            <section aria-label="Daftar pengguna" :aria-busy="listLoading">
                <div v-if="listLoading" class="card p-5 space-y-3" role="status">
                    <span class="sr-only">Memuat daftar pengguna</span>
                    <div v-for="n in 5" :key="n" class="skeleton h-14" />
                </div>
                <EmptyState
                    v-else-if="!users.length"
                    class="card"
                    :title="props.search ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna'"
                    :message="props.search ? 'Coba nama atau email lain, atau reset filter.' : 'Belum ada pengguna yang dapat ditampilkan.'"
                />
                <EmptyState
                    v-else-if="!filteredUsers.length"
                    class="card"
                    title="Pengguna tidak ditemukan"
                    message="Coba tenant lain atau reset filter untuk melihat semua pengguna."
                />
                <BaseTableContainer v-else class="users-table">
                    <table class="w-full text-sm" aria-label="Daftar pengguna platform">
                        <thead>
                            <tr class="text-left border-b b-line">
                                <th scope="col">Pengguna</th>
                                <th scope="col">Peran</th>
                                <th scope="col">Tenant</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.id" class="border-b b-line">
                                <td data-label="Pengguna">
                                    <p class="font-semibold t-ink break-words">{{ user.name }}</p>
                                    <p class="text-xs t-muted mt-1 break-all">{{ user.email }}</p>
                                </td>
                                <td data-label="Peran"><BaseBadge class="user-role-badge" :variant="roleVariant(user.role)">{{ roleLabel(user.role) }}</BaseBadge></td>
                                <td data-label="Tenant" class="t-muted">{{ user.tenant_name }}</td>
                                <td data-label="Status">
                                    <BaseBadge v-if="user.deleted_at" variant="danger">Dihapus · {{ user.deleted_at }}</BaseBadge>
                                    <BaseBadge v-else-if="user.is_active" variant="success">Aktif</BaseBadge>
                                    <BaseBadge v-else variant="neutral">Nonaktif</BaseBadge>
                                </td>
                                <td data-label="Tindakan" class="text-right">
                                    <BaseButton
                                        v-if="!user.deleted_at"
                                        variant="danger"
                                        size="sm"
                                        :aria-label="`Hapus pengguna ${user.name}`"
                                        @click="openDeleteModal(user)"
                                    >
                                        Hapus
                                    </BaseButton>
                                    <span v-else class="text-xs t-muted">Tidak tersedia</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </BaseTableContainer>
            </section>
        </div>

        <Modal :show="showDeleteModal" max-width="md" :closeable="!deleteProcessing" @close="closeDeleteModal">
            <div class="users-delete-dialog p-5 sm:p-6 space-y-5" role="alertdialog" aria-labelledby="delete-user-title" aria-describedby="delete-user-description" @keydown.tab="trapDeleteFocus">
                <div>
                    <h3 id="delete-user-title" class="font-display text-xl t-ink">Hapus pengguna?</h3>
                    <p id="delete-user-description" class="text-sm t-muted mt-2">
                        Pengguna <strong class="t-ink">{{ userToDelete?.name }}</strong> tidak akan dapat masuk lagi.
                    </p>
                </div>
                <BaseAlert v-if="errors.user_id" variant="danger">{{ errors.user_id }}</BaseAlert>
                <BaseAlert v-if="failure" variant="danger">{{ failure }}</BaseAlert>
                <div class="flex flex-wrap justify-end gap-3">
                    <BaseButton ref="cancelDeleteButton" variant="secondary" :disabled="deleteProcessing" @click="closeDeleteModal">Batal</BaseButton>
                    <BaseButton variant="danger" :loading="deleteProcessing" @click="confirmDelete">{{ deleteProcessing ? 'Menghapus…' : 'Hapus pengguna' }}</BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.platform-users-page { min-width: 0; overflow-wrap: anywhere; animation-duration: 200ms; }
.users-delete-dialog { overflow-wrap: anywhere; }
.users-hero { box-shadow: var(--shadow-sm); }
.users-table th, .users-table td { padding: 1rem; vertical-align: top; }
.users-table :deep(table) { table-layout: fixed; min-width: 720px; }
.users-table th:nth-child(1) { width: 32%; }
.users-table th:nth-child(2) { width: 16%; }
.users-table th:nth-child(3) { width: 20%; }
.users-table th:nth-child(4) { width: 20%; }
.users-table th:nth-child(5) { width: 12%; }
.users-table :deep(.user-role-badge) { color: var(--ink); }
.platform-users-page :deep(.base-btn--danger), .users-delete-dialog :deep(.base-btn--danger) { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
.platform-users-page :deep(.base-btn--danger:hover), .users-delete-dialog :deep(.base-btn--danger:hover) { box-shadow: 0 0 0 3px var(--danger-bg); }
.platform-users-page :deep(input), .platform-users-page :deep(select) { min-width: 0; width: 100%; }
.platform-users-page :deep(input:hover), .platform-users-page :deep(select:hover) { border-color: var(--brand); }
.platform-users-page :deep(input:active), .platform-users-page :deep(select:active) { border-color: var(--brand-strong); }
.users-table :deep(input:hover), .users-table :deep(select:hover) { border-color: var(--brand); }
.users-table :deep(input:focus-visible), .users-table :deep(select:focus-visible), .users-table :deep(button:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }

@media (max-width: 1199px) {
    .users-table :deep(.base-table-scroll) { overflow: visible; }
    .users-table thead { display: none; }
    .users-table table, .users-table tbody { display: block; min-width: 0; width: 100%; }
    .users-table tr { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: 1rem; }
    .users-table td { padding: 0; }
    .users-table td::before { content: attr(data-label); display: block; margin-bottom: .35rem; color: var(--muted); font-size: .75rem; font-weight: 600; }
    .users-table td:first-child { grid-column: 1 / -1; }
    .users-table td:last-child { text-align: left; }
}

@media (max-width: 479px) {
    .users-total { width: 100%; text-align: left; }
    .users-table tr { grid-template-columns: 1fr; }
    .users-table td:first-child { grid-column: auto; }
}
</style>
