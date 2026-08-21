<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ tenants: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showCreate = ref(false);
const form = ref({ name: '' });

const submit = () => {
    router.post(route('platform.tenants.store'), form.value, {
        onSuccess: () => {
            form.value = { name: '' };
            showCreate.value = false;
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
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500"
            >
                + Tambah Tenant
            </button>
        </div>

        <div v-if="showCreate" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Nama Organisasi</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm"
                        placeholder="PT Contoh Nusantara"
                    />
                    <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
                </div>
                <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Slug</th>
                        <th class="px-6 py-3 font-medium">Users</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ tenant.name }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ tenant.slug }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ tenant.users_count }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(tenant.status)">
                                {{ tenant.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>