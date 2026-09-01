<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ assignments: Array, users: Array, modules: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({ user_id: '', training_module_id: '' });

const sortBy = ref('terbaru'); // 'terbaru' | 'nama_az'

const sortedAssignments = computed(() => {
    const arr = [...props.assignments];
    if (sortBy.value === 'nama_az') {
        return arr.sort((a, b) => a.user.name.localeCompare(b.user.name));
    }
    // Default: terbaru (created_at desc sudah dari controller)
    return arr;
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const d = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
};

const submit = () => {
    router.post(route('tenant.assignments.store'), form.value, {
        onSuccess: () => {
            form.value = { user_id: '', training_module_id: '' };
            showForm.value = false;
        },
    });
};

const updateStatus = (assignment, status) => {
    router.patch(route('tenant.assignments.update', assignment.id), {
        status: status,
        score: status === 'completed' ? 100 : null, // Simplifikasi untuk MVP
    });
};
</script>

<template>
    <Head title="Training Assignments" />

    <AppLayout title="Training Assignments">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Tugaskan modul training kepada anggota organisasi Anda.
            </p>
            <div class="flex items-center gap-3">
                <select v-model="sortBy" class="input text-sm">
                    <option value="terbaru">Terbaru</option>
                    <option value="nama_az">Nama A-Z</option>
                </select>
                <button
                    @click="showForm = !showForm"
                    class="btn btn-primary"
                >
                    + Tugaskan Modul
                </button>
            </div>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <div class="font-semibold t-ink mb-4">Tugaskan Modul Baru</div>
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="text-sm t-muted">Pilih User</label>
                    <select v-model="form.user_id" required class="input mt-1 w-full">
                        <option value="" disabled>-- Pilih User --</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm t-muted">Pilih Modul</label>
                    <select v-model="form.training_module_id" required class="input mt-1 w-full">
                        <option value="" disabled>-- Pilih Modul --</option>
                        <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }} ({{ mod.duration_minutes }}m)</option>
                    </select>
                </div>
                <button class="btn btn-primary">Simpan</button>
            </form>
            <p v-if="errors.user_id" class="text-xs text-red-600 mt-2">{{ errors.user_id }}</p>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">User</th>
                        <th class="px-6 py-3 font-medium">Modul</th>
                        <th class="px-6 py-3 font-medium">Tanggal Penugasan</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="assignment in sortedAssignments" :key="assignment.id" class="border-b border-gray-50">
                        <td class="px-6 py-3">
                            <div class="font-medium t-ink">{{ assignment.user.name }}</div>
                            <div class="text-xs t-muted">{{ assignment.user.email }}</div>
                        </td>
                        <td class="px-6 py-3 t-ink">{{ assignment.module.title }}</td>
                        <td class="px-6 py-3 t-muted">{{ formatDate(assignment.created_at) }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" 
                                  :class="{
                                      'bg-yellow-100 text-yellow-700': assignment.status === 'assigned',
                                      'bg-blue-100 text-blue-700': assignment.status === 'in_progress',
                                      'bg-emerald-100 badge-ok': assignment.status === 'completed'
                                  }">
                                {{ assignment.status.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right space-x-2">
                            <button v-if="assignment.status !== 'completed'" 
                                    @click="updateStatus(assignment, 'completed')" 
                                    class="text-emerald-600 text-sm font-medium hover:badge-ok transition-colors">
                                Tandai Selesai
                            </button>
                        </td>
                    </tr>
                    <tr v-if="sortedAssignments.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center t-muted">Belum ada assignment.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>