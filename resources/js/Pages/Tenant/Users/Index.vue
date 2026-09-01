<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ users: Array });

const errors = computed(() => usePage().props.errors ?? {});

// --- Create User State ---
const showCreate = ref(false);
const createForm = ref({ name: '', email: '', role: 'user' });

const submitCreate = () => {
    router.post(route('tenant.users.store'), createForm.value, {
        onSuccess: () => {
            createForm.value = { name: '', email: '', role: 'user' };
            showCreate.value = false;
        },
    });
};

// --- Import CSV State ---
const showImport = ref(false);
const importForm = ref({ file: null });
const importErrors = computed(() => errors.value?.file ?? '');

const submitImport = () => {
    const formData = new FormData();
    formData.append('file', importForm.value.file);

    router.post(route('tenant.users.import'), formData, {
        forceFormData: true,
        onSuccess: () => {
            importForm.value = { file: null };
            showImport.value = false;
        },
    });
};

// --- Edit User State ---
const editingId = ref(null);
const editForm = ref({ name: '', email: '', role: 'user', is_active: true });

const startEdit = (user) => {
    editingId.value = user.id;
    editForm.value = { name: user.name, email: user.email, role: user.role, is_active: user.is_active };
};

const submitEdit = (user) => {
    router.patch(route('tenant.users.update', user.id), editForm.value, {
        onSuccess: () => (editingId.value = null),
    });
};

const roleBadge = (role) =>
    role === 'tenant_admin' ? 'bg-indigo-100 text-indigo-700' : 'badge-ok';
</script>

<template>
    <Head title="Users" />

    <AppLayout title="Organization Users">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Kelola anggota organisasi Anda. Perubahan role & status tercatat di audit log.
            </p>
            <div class="flex gap-2">
                <button
                    @click="showImport = !showImport"
                    class="btn btn-primary"
                >
                    Import CSV
                </button>
                <button
                    @click="showCreate = !showCreate"
                    class="btn btn-primary"
                >
                    + Tambah User
                </button>
            </div>
        </div>

        <!-- Form Import CSV -->
        <div v-if="showImport" class="card p-6 mb-6">
            <div class="font-semibold t-ink mb-4">Import User via CSV</div>
            <form @submit.prevent="submitImport" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">File CSV (Format: name, email, role)</label>
                    <input
                        @change="importForm.file = $event.target.files[0]"
                        type="file"
                        accept=".csv,.txt"
                        required
                        class="input mt-1 w-full"
                    />
                    <p v-if="importErrors" class="text-xs text-red-600 mt-1">{{ importErrors }}</p>
                </div>
                <button class="btn btn-primary">Upload & Import</button>
            </form>
            <p class="text-xs text-gray-400 mt-3">
                Maksimal 500 baris. Jika ada 1 baris error, seluruh import akan dibatalkan.
            </p>
        </div>

        <!-- Form Tambah User -->
        <div v-if="showCreate" class="card p-6 mb-6">
            <div class="font-semibold t-ink mb-4">User Baru</div>
            <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm t-muted">Nama</label>
                    <input v-model="createForm.name" type="text" required class="input mt-1 w-full" />
                    <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
                </div>
                <div>
                    <label class="text-sm t-muted">Email</label>
                    <input v-model="createForm.email" type="email" required class="input mt-1 w-full" />
                    <p v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</p>
                </div>
                <div>
                    <label class="text-sm t-muted">Role</label>
                    <select v-model="createForm.role" class="input mt-1 w-full">
                        <option value="user">User</option>
                        <option value="tenant_admin">Tenant Admin</option>
                    </select>
                    <p v-if="errors.role" class="text-xs text-red-600 mt-1">{{ errors.role }}</p>
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
            <p class="text-xs text-gray-400 mt-3">
                User baru dibuat dengan password sementara acak; akses diberikan lewat flow reset password.
            </p>
        </div>

        <!-- Tabel User -->
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Email</th>
                        <th class="px-6 py-3 font-medium">Role</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-b b-line">
                        <template v-if="editingId === user.id">
                            <td class="px-6 py-3"><input v-model="editForm.name" class="input w-full" /></td>
                            <td class="px-6 py-3"><input v-model="editForm.email" type="email" class="input w-full" /></td>
                            <td class="px-6 py-3">
                                <select v-model="editForm.role" class="input w-full">
                                    <option value="user">User</option>
                                    <option value="tenant_admin">Tenant Admin</option>
                                </select>
                            </td>
                            <td class="px-6 py-3">
                                <select v-model="editForm.is_active" class="input w-full">
                                    <option :value="true">Active</option>
                                    <option :value="false">Disabled</option>
                                </select>
                            </td>
                            <td class="px-6 py-3 text-right space-x-2">
                                <button @click="submitEdit(user)" class="text-indigo-600 text-sm font-medium">Simpan</button>
                                <button @click="editingId = null" class="text-gray-400 text-sm">Batal</button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-6 py-3 font-medium t-ink">{{ user.name }}</td>
                            <td class="px-6 py-3 t-muted">{{ user.email }}</td>
                            <td class="px-6 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="roleBadge(user.role)">
                                    {{ user.role === 'tenant_admin' ? 'Tenant Admin' : 'User' }}
                                </span>
                            </td>
                            <td class="px-6 py-3">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="user.is_active ? 'badge-ok' : 'bg-red-100 text-red-700'"
                                >
                                    {{ user.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button @click="startEdit(user)" class="text-indigo-600 text-sm font-medium">Edit</button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>