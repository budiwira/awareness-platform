<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ users: Array, search: String });

const errors = computed(() => usePage().props.errors ?? {});
const searchInput = ref('');
const showDeleteModal = ref(false);
const userToDelete = ref(null);

const handleSearch = () => {
    router.get(route('platform.users.index'), { search: searchInput.value }, {
        preserveState: true,
        replace: true,
    });
};

const openDeleteModal = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!userToDelete.value) return;
    router.post(route('platform.users.destroy'), { user_id: userToDelete.value.id }, {
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
        },
    });
};

const roleBadge = (role) => {
    if (role === 'super_admin') return 'bg-purple-100 text-purple-700';
    if (role === 'tenant_admin') return 'bg-blue-100 text-blue-700';
    return 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <Head title="Users (Cross-Tenant)" />

    <AppLayout title="Users (Cross-Tenant)">
        <div class="mb-6 flex items-center gap-4">
            <div class="flex-1">
                <input
                    v-model="searchInput"
                    @keyup.enter="handleSearch"
                    type="text"
                    placeholder="Cari nama atau email..."
                    class="input w-full max-w-md"
                />
            </div>
            <button @click="handleSearch" class="btn btn-primary">
                Cari
            </button>
        </div>

        <div v-if="errors.user_id" class="mb-4 p-4 bg-red-50 text-red-700 rounded-xl text-sm">
            {{ errors.user_id }}
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium">Tenant</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <p>Tidak ada user ditemukan</p>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="user in users" :key="user.id" class="border-b border-gray-50 hover:bg-gray-25 transition-all">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ user.name }}</td>
                        <td class="px-6 py-3 text-gray-600">{{ user.email }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="roleBadge(user.role)">
                                {{ user.role }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ user.tenant_name }}</td>
                        <td class="px-6 py-3">
                            <span v-if="user.deleted_at" class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                Dihapus {{ user.deleted_at }}
                            </span>
                            <span v-else-if="user.is_active" class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                Aktif
                            </span>
                            <span v-else class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                Nonaktif
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <button
                                v-if="!user.deleted_at"
                                @click="openDeleteModal(user)"
                                class="btn btn-danger text-xs py-1 px-3"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Delete Modal -->
        <div v-if="showDeleteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 fade-in">
            <div class="card p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-2">Konfirmasi Hapus User</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Anda yakin ingin menghapus user <strong>{{ userToDelete?.name }}</strong> ({{ userToDelete?.email }})?
                    User ini tidak akan bisa login lagi.
                </p>
                <div class="flex gap-3 justify-end">
                    <button @click="showDeleteModal = false" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                        Batal
                    </button>
                    <button @click="confirmDelete" class="btn btn-danger">
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
