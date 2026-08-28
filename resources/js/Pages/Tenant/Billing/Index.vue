<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    plans: Array,
    current_plan: Object,
    current_subscription: Object,
    user_count: Number,
});

const errors = computed(() => usePage().props.errors ?? {});

const choose = (planId) => {
    router.post(route('tenant.billing.subscribe'), { plan_id: planId });
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');
</script>

<template>
    <Head title="Billing" />

    <AppLayout title="Billing & Langganan">
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6 flex items-center justify-between">
            <div>
                <div class="text-sm text-gray-500">Plan aktif saat ini</div>
                <div class="text-xl font-bold text-gray-900">{{ current_plan?.name ?? 'Free' }}</div>
                <div class="text-xs text-gray-400 mt-1">
                    {{ user_count }} users terpakai · maks. {{ current_plan?.max_users }} users
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-indigo-600">{{ formatPrice(current_plan?.price_monthly ?? 0) }}</div>
                <div v-if="current_subscription" class="text-xs text-gray-400 mt-1">
                    sejak {{ new Date(current_subscription.started_at).toLocaleDateString('id-ID') }}
                </div>
            </div>
        </div>

        <p v-if="errors.plan_id" class="mb-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            {{ errors.plan_id }}
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div v-for="plan in plans" :key="plan.id"
                class="bg-white rounded-xl shadow-sm p-6 flex flex-col"
                :class="plan.id === current_plan?.id ? 'ring-2 ring-indigo-500' : ''">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900">{{ plan.name }}</h3>
                    <span v-if="plan.id === current_plan?.id"
                        class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                        aktif
                    </span>
                </div>
                <div class="text-2xl font-bold text-indigo-600 mb-3">{{ formatPrice(plan.price_monthly) }}</div>
                <div class="text-xs text-gray-500 mb-3">Maks. {{ plan.max_users }} users</div>
                <ul class="space-y-1 text-sm text-gray-600 flex-1 mb-4">
                    <li v-for="(f, i) in plan.features" :key="i" class="flex items-start gap-2">
                        <span class="text-emerald-500">✓</span> {{ f }}
                    </li>
                </ul>
                <button
                    v-if="plan.id !== current_plan?.id"
                    @click="choose(plan.id)"
                    class="w-full px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500">
                    Pilih Plan Ini
                </button>
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-6">
            * Simulasi billing untuk demo — tidak ada pembayaran sungguhan.
        </p>
    </AppLayout>
</template>