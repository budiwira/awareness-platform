<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ modules: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const editingId = ref(null);
const form = ref({ title: '', description: '', content: '', duration_minutes: 10, is_active: true });

const startCreate = () => {
    editingId.value = null;
    form.value = { title: '', description: '', content: '', duration_minutes: 10, is_active: true };
    showForm.value = true;
};

const startEdit = (module) => {
    editingId.value = module.id;
    form.value = { ...module };
    showForm.value = true;
};

const submit = () => {
    if (editingId.value) {
        router.patch(route('platform.modules.update', editingId.value), form.value, {
            onSuccess: () => (showForm.value = false),
        });
    } else {
        router.post(route('platform.modules.store'), form.value, {
            onSuccess: () => (showForm.value = false),
        });
    }
};

const destroy = (id) => {
    if (confirm('Hapus modul ini?')) {
        router.delete(route('platform.modules.destroy', id));
    }
};
</script>

<template>
    <Head title="Training Modules" />

    <AppLayout title="Content Library">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">
                Kelola materi training global. Tenant Admin akan menugaskan modul ini ke user mereka.
            </p>
            <button
                @click="startCreate"
                class="btn btn-primary"
            >
                + Buat Modul Baru
            </button>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <div class="font-semibold text-gray-800 mb-4">{{ editingId ? 'Edit Modul' : 'Modul Baru' }}</div>
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Judul</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Deskripsi Singkat</label>
                    <textarea v-model="form.description" rows="2" class="input mt-1 w-full"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Konten Materi (HTML/Text)</label>
                    <textarea v-model="form.content" rows="6" required class="input mt-1 w-full"></textarea>
                    <p v-if="errors.content" class="text-xs text-red-600 mt-1">{{ errors.content }}</p>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Durasi (menit)</label>
                    <input v-model.number="form.duration_minutes" type="number" min="1" required class="input mt-1 w-full" />
                </div>
                <div v-if="editingId" class="flex items-center pt-6">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                        Modul Aktif
                    </label>
                </div>
                <div class="md:col-span-2 flex justify-end gap-2">
                    <button type="button" @click="showForm = false" class="btn btn-secondary">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Judul</th>
                        <th class="px-6 py-3 font-medium">Durasi</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="module in modules" :key="module.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ module.title }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ module.duration_minutes }} menit</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="module.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                {{ module.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right space-x-2">
                            <button @click="startEdit(module)" class="text-indigo-600 text-sm font-medium">Edit</button>
                            <button @click="destroy(module.id)" class="text-red-600 text-sm font-medium">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>