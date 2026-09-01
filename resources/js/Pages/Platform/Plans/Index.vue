<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ plans: Array, modules: Array });
const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const editingPlan = ref(null);

const allFeatures = ['training', 'reports_export', 'ttx', 'case_studies', 'ctf'];

const form = ref({
  name: '',
  price_monthly: 0,
  max_users: 5,
  features: [],
  includes_all_modules: false,
  module_ids: [],
});

const openCreate = () => {
  editingPlan.value = null;
  form.value = { name: '', price_monthly: 0, max_users: 5, features: [], includes_all_modules: false, module_ids: [] };
  showForm.value = true;
};

const openEdit = (plan) => {
  editingPlan.value = plan;
  form.value = {
    name: plan.name,
    price_monthly: plan.price_monthly,
    max_users: plan.max_users,
    features: plan.features ?? [],
    includes_all_modules: plan.includes_all_modules ?? false,
    module_ids: plan.modules?.map(m => m.id) ?? [],
  };
  showForm.value = true;
};

const submit = () => {
  if (editingPlan.value) {
    router.put(route('platform.plans.update', editingPlan.value.id), form.value, {
      onSuccess: () => { showForm.value = false; editingPlan.value = null; },
    });
  } else {
    router.post(route('platform.plans.store'), form.value, {
      onSuccess: () => { showForm.value = false; },
    });
  }
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');
</script>

<template>
  <Head title="Plans & Billing" />

  <AppLayout title="Plans & Billing">
    <div class="flex items-center justify-between mb-6">
      <p class="text-sm t-muted">Kelola paket langganan platform.</p>
      <button @click="openCreate" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors">
        + Buat Plan
      </button>
    </div>

    <div v-if="showForm" class="card p-6 mb-6">
      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm t-muted">Nama Plan</label>
          <input v-model="form.name" type="text" required class="input mt-1 w-full" />
          <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
        </div>
        <div>
          <label class="text-sm t-muted">Harga (ribuan IDR / bulan)</label>
          <input v-model.number="form.price_monthly" type="number" min="0" class="input mt-1 w-full" />
        </div>
        <div>
          <label class="text-sm t-muted">Maks. Users</label>
          <input v-model.number="form.max_users" type="number" min="1" class="input mt-1 w-full" />
        </div>
        <div class="flex items-center gap-2 pt-6">
          <input v-model="form.includes_all_modules" type="checkbox" id="includes_all" class="rounded b-line" />
          <label for="includes_all" class="text-sm t-muted">Semua modul published</label>
        </div>
        <div v-if="!form.includes_all_modules" class="md:col-span-2">
          <label class="text-sm t-muted">Modul Kurasi</label>
          <select v-model="form.module_ids" multiple class="input mt-1 w-full h-28">
            <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }}</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">Pilih beberapa modul (Ctrl+klik)</p>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm t-muted">Fitur</label>
          <div class="flex flex-wrap gap-3 mt-2">
            <label v-for="f in allFeatures" :key="f" class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" :value="f" v-model="form.features" class="rounded b-line" />
              {{ f }}
            </label>
          </div>
        </div>
        <div class="md:col-span-2 flex justify-end gap-2">
          <button type="button" @click="showForm = false" class="px-4 py-2 rounded-lg border b-line t-muted hover:bg-app">Batal</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium">Simpan</button>
        </div>
      </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="plan in plans" :key="plan.id" class="card p-6 flex flex-col">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-semibold t-ink">{{ plan.name }}</h3>
          <span class="text-xs text-gray-400">{{ plan.subscriptions_count }} subscriber</span>
        </div>
        <div class="text-2xl font-bold text-indigo-600 mb-1">{{ formatPrice(plan.price_monthly) }}</div>
        <div class="text-xs t-muted mb-2">Maks. {{ plan.max_users }} users · {{ plan.includes_all_modules ? 'Semua modul published' : (plan.modules?.length ?? 0) + ' modul kurasi' }}</div>
        <div class="flex flex-wrap gap-1 mb-3">
          <span v-for="f in plan.features" :key="f" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium chip-brand dark:bg-teal-900/30 chip-brand dark:text-teal-300  dark:border-teal-800">{{ f }}</span>
        </div>
        <ul class="space-y-1 text-sm t-muted flex-1">
          <li v-for="m in plan.modules" :key="m.id" class="flex items-start gap-2 text-xs">📚 {{ m.title }}</li>
        </ul>
        <button @click="openEdit(plan)" class="mt-3 text-xs text-blue-600 hover:text-blue-700 font-medium">Edit</button>
      </div>
    </div>
  </AppLayout>
</template>
