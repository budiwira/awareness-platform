<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ tenants: Array, packages: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showCreate = ref(false);
const showSetPlan = ref(false);
const selectedTenant = ref(null);
const form = ref({ name: '' });
const planForm = ref({ tenant_id: null, package_id: null });

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
    planForm.value = { tenant_id: tenant.id, package_id: null };
    showSetPlan.value = true;
};

const submitSetPlan = () => {
    router.post(route('platform.tenants.set-package'), planForm.value, {
        onSuccess: () => {
            showSetPlan.value = false;
            selectedTenant.value = null;
        },
    });
};

const statusBadge = (status) =>
    status === 'active' ? 'badge-ok' : 'bg-red-100 text-red-700';
</script>

<template>
    <Head title="Tenants" />

    <AppLayout title="Organizations">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
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
                    <label class="text-sm t-muted">Nama Organisasi</label>
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

        <div v-if="errors.package_id" class="mb-4 p-4 bg-red-50 text-red-700 rounded-xl text-sm">
            {{ errors.package_id }}
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Users</th>
                        <th class="px-6 py-3 font-medium">Package</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-b b-line hover:bg-surface2 transition-all">
                        <td class="px-6 py-3 font-medium t-ink">{{ tenant.name }}</td>
                        <td class="px-6 py-3 t-muted">{{ tenant.slug }}</td>
                        <td class="px-6 py-3 t-muted">{{ tenant.users_count }}</td>
                        <td class="px-6 py-3">
                            <span v-if="tenant.current_plan" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ tenant.current_plan }}</span>
                            <span v-else class="text-xs t-muted">Belum ada paket</span>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(tenant.status)">
                                {{ tenant.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <Link :href="route('platform.tenants.user-access.show', tenant.id)" class="btn bg-surface2 hover:bg-surface2 t-ink text-xs py-1 px-3 mr-2">Kelola Akses</Link>
                            <button @click="openSetPlan(tenant)" class="btn btn-primary text-xs py-1 px-3">
                                Set Package
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Set Package Modal -->
        <div v-if="showSetPlan" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 fade-in">
            <div class="card p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Set Package untuk {{ selectedTenant?.name }}</h3>
                <form @submit.prevent="submitSetPlan" class="space-y-4">
                    <div>
                        <label class="text-sm t-muted">Pilih Package</label>
                        <select v-model="planForm.package_id" required class="input mt-1 w-full">
                            <option :value="null" disabled>-- Pilih Package --</option>
                            <option v-for="Package in packages" :key="Package.id" :value="Package.id">
                                {{ Package.name }} ({{ Package.max_users }} users max)
                            </option>
                        </select>
                    </div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="showSetPlan = false" class="btn bg-surface2 hover:bg-surface2 t-ink">
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