<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ plans: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({ name: '', price_monthly: 0, max_users: 5, features: '' });

const submit = () => {
    router.post(route('platform.plans.store'), form.value, {
        onSuccess: () => (showForm.value = false),
    });
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');
</script>

<template>
    <Head title="Plans & Billing" />

    <AppLayout title="Plans & Billing">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">Kelola paket langganan platform.</p>
            <button @click="showForm = !showForm" class="btn btn-primary">
                + Buat Plan
            </button>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-600">Nama Plan</label>
                    <input v-model="form.name" type="text" required class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Harga (ribuan IDR / bulan)</label>
                    <input v-model.number="form.price_monthly" type="number" min="0" class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Maks. Users</label>
                    <input v-model.number="form.max_users" type="number" min="1" class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Fitur (satu per baris)</label>
                    <textarea v-model="form.features" rows="3" class="input mt-1 w-full"></textarea>
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="plan in plans" :key="plan.id" class="card p-6 flex flex-col">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900">{{ plan.name }}</h3>
                    <span class="text-xs text-gray-400">{{ plan.subscriptions_count }} subscriber</span>
                </div>
                <div class="text-2xl font-bold text-indigo-600 mb-3">{{ formatPrice(plan.price_monthly) }}</div>
                <div class="text-xs text-gray-500 mb-3">Maks. {{ plan.max_users }} users</div>
                <ul class="space-y-1 text-sm text-gray-600 flex-1">
                    <li v-for="(f, i) in plan.features" :key="i" class="flex items-start gap-2">
                        <span class="text-emerald-500">✓</span> {{ f }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>