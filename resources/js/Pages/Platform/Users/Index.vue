<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
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
const searchInput = ref(props.search);
const tenantSelect = ref('');
const showDeleteModal = ref(false);
const userToDelete = ref(null);
const loading = ref(false);
const failure = ref('');

const tenantOptions = computed(() => {
    const tenants = new Map();
    props.users.forEach(user => {
        if (user.tenant_id && user.tenant_name) tenants.set(user.tenant_id, user.tenant_name);
    });

    return [...tenants.entries()]
        .map(([id, name]) => ({ id, name }))
        .sort((a, b) => a.name.localeCompare(b.name, 'id'));
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
    failure.value = '';
    loading.value = true;
    router.get(route('platform.users.index'), { search: searchInput.value.trim() || null }, {
        preserveState: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
};

const resetFilters = () => {
    searchInput.value = '';
    tenantSelect.value = '';
    handleSearch();
};

const openDeleteModal = user => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    if (!loading.value) {
        showDeleteModal.value = false;
        userToDelete.value = null;
    }
};

const confirmDelete = () => {
    if (!userToDelete.value || loading.value) return;
    failure.value = '';
    loading.value = true;
    router.post(route('platform.users.destroy'), { user_id: userToDelete.value.id }, {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
        onFinish: () => { loading.value = false; },
    });
};

const stopException = router.on('exception', event => {
    if (!loading.value) return;
    event.preventDefault();
    loading.value = false;
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
                        <p class="text-xs t-muted">Total pengguna</p>
                        <p class="font-display text-2xl t-ink tabular-nums">{{ users.length }}</p>
                    </div>
                </div>
            </section>

            <BaseAlert v-if="errors.user_id" variant="danger">{{ errors.user_id }}</BaseAlert>
            <BaseAlert v-if="failure" variant="danger">{{ failure }}</BaseAlert>

            <section aria-label="Filter pengguna" class="card p-4 sm:p-5 space-y-4" :aria-busy="loading">
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,16rem)_auto] gap-4 items-end">
                    <BaseInput
                        id="user-search"
                        v-model="searchInput"
                        type="search"
                        label="Cari pengguna"
                        placeholder="Nama atau email"
                        @keyup.enter="handleSearch"
                    />
                    <BaseSelect id="user-tenant" v-model="tenantSelect" label="Tenant">
                        <option value="">Semua tenant</option>
                        <option v-for="tenant in tenantOptions" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
                    </BaseSelect>
                    <div class="flex flex-wrap gap-2">
                        <BaseButton :loading="loading" @click="handleSearch">{{ loading ? 'Memuat…' : 'Cari' }}</BaseButton>
                        <BaseButton v-if="hasFilters" variant="ghost" :disabled="loading" @click="resetFilters">Reset</BaseButton>
                    </div>
                </div>
                <p class="text-sm t-muted" role="status">{{ filteredUsers.length }} dari {{ users.length }} pengguna ditampilkan</p>
            </section>

            <section aria-label="Daftar pengguna" :aria-busy="loading">
                <div v-if="loading" class="card p-5 space-y-3" role="status">
                    <span class="sr-only">Memuat daftar pengguna</span>
                    <div v-for="n in 5" :key="n" class="skeleton h-14" />
                </div>
                <EmptyState
                    v-else-if="!users.length"
                    class="card"
                    title="Belum ada pengguna"
                    message="Tidak ada pengguna yang cocok dengan pencarian saat ini."
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
                                <td data-label="Peran"><BaseBadge :variant="roleVariant(user.role)">{{ roleLabel(user.role) }}</BaseBadge></td>
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

        <Modal :show="showDeleteModal" max-width="md" :closeable="!loading" @close="closeDeleteModal">
            <div class="p-5 sm:p-6 space-y-5" role="alertdialog" aria-labelledby="delete-user-title" aria-describedby="delete-user-description">
                <div>
                    <h3 id="delete-user-title" class="font-display text-xl t-ink">Hapus pengguna?</h3>
                    <p id="delete-user-description" class="text-sm t-muted mt-2">
                        Pengguna <strong class="t-ink">{{ userToDelete?.name }}</strong> tidak akan dapat masuk lagi.
                    </p>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <BaseButton variant="secondary" :disabled="loading" @click="closeDeleteModal">Batal</BaseButton>
                    <BaseButton variant="danger" :loading="loading" @click="confirmDelete">{{ loading ? 'Menghapus…' : 'Hapus pengguna' }}</BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.platform-users-page { min-width: 0; overflow-wrap: anywhere; }
.users-hero { box-shadow: var(--shadow-sm); }
.users-table th, .users-table td { padding: 1rem; vertical-align: top; }
.users-table :deep(table) { table-layout: fixed; min-width: 720px; }
.users-table th:nth-child(1) { width: 32%; }
.users-table th:nth-child(2) { width: 16%; }
.users-table th:nth-child(3) { width: 20%; }
.users-table th:nth-child(4) { width: 20%; }
.users-table th:nth-child(5) { width: 12%; }
.users-table :deep(input:hover), .users-table :deep(select:hover) { border-color: var(--brand); }
.users-table :deep(input:focus-visible), .users-table :deep(select:focus-visible), .users-table :deep(button:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }

@media (max-width: 1199px) {
    .users-table :deep(.base-table-scroll) { overflow: visible; }
    .users-table thead { display: none; }
    .users-table table, .users-table tbody { display: block; }
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
