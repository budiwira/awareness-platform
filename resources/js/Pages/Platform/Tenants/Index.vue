<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ tenants: Array, plans: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showCreate = ref(false);
const showSetPlan = ref(false);
const selectedTenant = ref(null);
const form = ref({ name: '' });
const planForm = ref({ tenant_id: null, plan_id: null });

const submit = () => {
    router.post(route('platform.tenants.store'), form.value, {
        onSuccess: () => {
            form.value = { name: '' };
            showCreate.value = false;
        },
    });
};

const openSetPlan = (tenant) => {
    selectedTenant.value = tenant;
    planForm.value = { tenant_id: tenant.id, plan_id: null };
    showSetPlan.value = true;
};

const submitSetPlan = () => {
    router.post(route('platform.tenants.set-plan'), planForm.value, {
        onSuccess: () => {
            showSetPlan.value = false;
            selectedTenant.value = null;
        },
    });
};

const statusBadge = (status) =>
    status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700';
</script>

<template>
    <Head title="Tenants" />

    <AppLayout title="Organizations">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">
                Provisioning organisasi pelanggan. Setiap tenant terisolasi penuh oleh RLS.
            </p>
            <button
                @click="showCreate = !showCreate"
                class="btn btn-primary"
            >
                + Tambah Tenant
            </button>
        </div>

        <div v-if="showCreate" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Nama Organisasi</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="input mt-1 w-full"
                        placeholder="PT Contoh Nusantara"
                    />
                    <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
                </div>
                <button class="btn btn-primary">Simpan</button>
            </form>
        </div>

        <div v-if="errors.plan_id" class="mb-4 p-4 bg-red-50 text-red-700 rounded-xl text-sm">
            {{ errors.plan_id }}
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Users</th>
                        <th class="px-6 py-3 font-medium">Plan</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-b border-gray-50 hover:bg-gray-25 transition-all">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ tenant.name }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ tenant.slug }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ tenant.users_count }}</td>
                        <td class="px-6 py-3">
                            <span v-if="tenant.current_plan" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ tenant.current_plan }}</span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(tenant.status)">
                                {{ tenant.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <button @click="openSetPlan(tenant)" class="btn btn-primary text-xs py-1 px-3">
                                Set Plan
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Set Plan Modal -->
        <div v-if="showSetPlan" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 fade-in">
            <div class="card p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Set Plan untuk {{ selectedTenant?.name }}</h3>
                <form @submit.prevent="submitSetPlan" class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-600">Pilih Plan</label>
                        <select v-model="planForm.plan_id" required class="input mt-1 w-full">
                            <option :value="null" disabled>-- Pilih Plan --</option>
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} ({{ plan.max_users }} users max)
                            </option>
                        </select>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="showSetPlan = false" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>