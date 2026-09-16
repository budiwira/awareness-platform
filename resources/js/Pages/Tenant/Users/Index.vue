<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseButton, BaseInput, BaseSelect } from '@/Components';

defineProps({ users: Array });

// --- Create User State ---
const showCreate = ref(false);
const createForm = useForm({ name: '', email: '' });

const submitCreate = () => {
    if (createForm.processing) return;
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
    if (importForm.processing) return;
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
    if (editForm.processing) return;
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
                <BaseButton
                    @click="showImport = !showImport"
                    :disabled="importForm.processing"
                >
                    Import CSV
                </BaseButton>
                <BaseButton
                    @click="showCreate = !showCreate"
                    :disabled="createForm.processing"
                >
                    Tambah learner
                </BaseButton>
            </div>
        </div>

        <!-- Form Import CSV -->
        <div v-if="showImport" class="card p-6 mb-6 fade-in">
            <div class="font-semibold t-ink mb-4">Import learner melalui CSV</div>
            <form @submit.prevent="submitImport" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label for="learner-import-file" class="text-sm t-muted">File CSV (format: name,email atau name,email,role)</label>
                    <input
                        id="learner-import-file"
                        @change="importForm.file = $event.target.files[0]"
                        type="file"
                        accept=".csv,.txt"
                        required
                        class="input mt-1 w-full"
                        :disabled="importForm.processing"
                        :aria-invalid="Boolean(importForm.errors.file)"
                        :aria-describedby="importForm.errors.file ? 'learner-import-file-error' : undefined"
                    />
                    <p v-if="importForm.errors.file" id="learner-import-file-error" class="text-xs mt-1" style="color: var(--danger)" role="alert">{{ importForm.errors.file }}</p>
                </div>
                <BaseButton type="submit" :loading="importForm.processing" :disabled="!importForm.file">{{ importForm.processing ? 'Mengimpor...' : 'Unggah dan import' }}</BaseButton>
            </form>
            <p class="text-xs t-muted mt-3">
                Maksimal 500 baris. Kolom role lama tetap diterima hanya jika nilainya "user". Satu baris tidak valid membatalkan seluruh import.
            </p>
        </div>

        <!-- Form Tambah User -->
        <div v-if="showCreate" class="card p-6 mb-6 fade-in">
            <div class="font-semibold t-ink mb-4">Learner baru</div>
            <form @submit.prevent="submitCreate" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <BaseInput v-model="createForm.name" label="Nama" required :error="createForm.errors.name" :disabled="createForm.processing" />
                <BaseInput v-model="createForm.email" label="Email" type="email" required :error="createForm.errors.email" :disabled="createForm.processing" />
                <div class="md:col-span-2 flex justify-end">
                    <BaseButton type="submit" :loading="createForm.processing">{{ createForm.processing ? 'Menyimpan...' : 'Simpan learner' }}</BaseButton>
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
                            <td class="px-6 py-3"><BaseInput v-model="editForm.name" label="Nama" :error="editForm.errors.name" :disabled="editForm.processing" /></td>
                            <td class="px-6 py-3"><BaseInput v-model="editForm.email" label="Email" type="email" :error="editForm.errors.email" :disabled="editForm.processing" /></td>
                            <td class="px-6 py-3">
                                <BaseSelect v-model="editForm.is_active" label="Status" :error="editForm.errors.is_active" :disabled="editForm.processing">
                                    <option :value="true">Aktif</option>
                                    <option :value="false">Nonaktif</option>
                                </BaseSelect>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <div class="flex justify-end items-center gap-4">
                                    <BaseButton size="sm" :loading="editForm.processing" @click="submitEdit(user)">{{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                                    <BaseButton size="sm" variant="ghost" :disabled="editForm.processing" @click="editingId = null">Batal</BaseButton>
                                </div>
                            </td>
                        </template>
                        <template v-else>
                            <td class="px-6 py-3 font-medium t-ink">{{ user.name }}</td>
                            <td class="px-6 py-3 t-muted">{{ user.email }}</td>
                            <td class="px-6 py-3">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="user.is_active ? 'badge-ok' : 'badge-danger'"
                                >
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <BaseButton size="sm" variant="ghost" @click="startEdit(user)">Edit</BaseButton>
                            </td>
                        </template>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td colspan="4" class="px-6 py-12 text-center">
                            <div class="font-semibold t-ink">Belum ada learner</div>
                            <p class="text-sm t-muted mt-1">Tambahkan learner pertama atau import daftar learner melalui CSV.</p>
                            <BaseButton class="mt-4" @click="showCreate = true">Tambah learner</BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
