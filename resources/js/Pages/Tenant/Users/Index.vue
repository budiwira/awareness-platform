<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ users: Array });

// --- Create User State ---
const showCreate = ref(false);
const createForm = useForm({ name: '', email: '' });

const submitCreate = () => {
    createForm.post(route('tenant.users.store'), {
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};

// --- Import CSV State ---
const showImport = ref(false);
const importForm = useForm({ file: null });

const submitImport = () => {
    importForm.post(route('tenant.users.import'), {
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            showImport.value = false;
        },
    });
};

// --- Edit User State ---
const editingId = ref(null);
const editForm = useForm({ name: '', email: '', is_active: true });

const startEdit = (user) => {
    editingId.value = user.id;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.is_active = user.is_active;
};

const submitEdit = (user) => {
    editForm.patch(route('tenant.users.update', user.id), {
        onSuccess: () => (editingId.value = null),
    });
};
</script>

<template>
    <Head title="Users" />

    <AppLayout title="Learner Organisasi">
        <div class="flex items-end justify-between gap-6 mb-8 flex-wrap">
            <div>
                <div class="text-sm font-medium t-muted mb-2">Manajemen learner</div>
                <h1 class="font-display text-2xl font-bold t-ink">Anggota organisasi</h1>
                <p class="text-sm t-muted mt-2">Kelola profil dan status learner. Penugasan modul dikelola melalui halaman Penugasan.</p>
            </div>
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
                    Tambah learner
                </button>
            </div>
        </div>

        <!-- Form Import CSV -->
        <div v-if="showImport" class="card p-6 mb-6 fade-in">
            <div class="font-semibold t-ink mb-4">Import learner melalui CSV</div>
            <form @submit.prevent="submitImport" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">File CSV (format: name,email atau name,email,role)</label>
                    <input
                        @change="importForm.file = $event.target.files[0]"
                        type="file"
                        accept=".csv,.txt"
                        required
                        class="input mt-1 w-full"
                    />
                    <p v-if="importForm.errors.file" class="text-xs text-red-600 mt-1" role="alert">{{ importForm.errors.file }}</p>
                </div>
                <button class="btn btn-primary" :disabled="importForm.processing || !importForm.file">
                    {{ importForm.processing ? 'Mengimpor...' : 'Unggah dan import' }}
                </button>
            </form>
            <p class="text-xs t-muted mt-3">
                Maksimal 500 baris. Kolom role lama tetap diterima hanya jika nilainya "user". Satu baris tidak valid membatalkan seluruh import.
            </p>
        </div>

        <!-- Form Tambah User -->
        <div v-if="showCreate" class="card p-6 mb-6 fade-in">
            <div class="font-semibold t-ink mb-4">Learner baru</div>
            <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm t-muted">Nama</label>
                    <input v-model="createForm.name" type="text" required class="input mt-1 w-full" />
                    <p v-if="createForm.errors.name" class="text-xs text-red-600 mt-1" role="alert">{{ createForm.errors.name }}</p>
                </div>
                <div>
                    <label class="text-sm t-muted">Email</label>
                    <input v-model="createForm.email" type="email" required class="input mt-1 w-full" />
                    <p v-if="createForm.errors.email" class="text-xs text-red-600 mt-1" role="alert">{{ createForm.errors.email }}</p>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Menyimpan...' : 'Simpan learner' }}
                    </button>
                </div>
            </form>
            <p class="text-xs t-muted mt-3">
                Learner baru dibuat dengan password sementara acak dan menggunakan alur reset password.
            </p>
        </div>

        <!-- Tabel User -->
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Email</th>
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
                                <select v-model="editForm.is_active" class="input w-full">
                                    <option :value="true">Aktif</option>
                                    <option :value="false">Nonaktif</option>
                                </select>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex justify-end items-center gap-4">
                                    <button @click="submitEdit(user)" class="text-indigo-600 text-sm font-medium" :disabled="editForm.processing">
                                        {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
                                    </button>
                                    <button @click="editingId = null" class="t-muted text-sm" :disabled="editForm.processing">Batal</button>
                                </div>
                                <p v-if="editForm.errors.name || editForm.errors.email || editForm.errors.is_active" class="text-xs text-red-600 mt-2" role="alert">
                                    {{ editForm.errors.name || editForm.errors.email || editForm.errors.is_active }}
                                </p>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-6 py-3 font-medium t-ink">{{ user.name }}</td>
                            <td class="px-6 py-3 t-muted">{{ user.email }}</td>
                            <td class="px-6 py-3">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="user.is_active ? 'badge-ok' : 'bg-red-100 text-red-700'"
                                >
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button @click="startEdit(user)" class="text-indigo-600 text-sm font-medium">Edit</button>
                            </td>
                        </template>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="font-semibold t-ink">Belum ada learner</div>
                            <p class="text-sm t-muted mt-1">Tambahkan learner pertama atau import daftar learner melalui CSV.</p>
                            <button @click="showCreate = true" class="btn btn-primary mt-4">Tambah learner</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
