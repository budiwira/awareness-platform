<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ packages: Array, modules: Array });
const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const editingPlan = ref(null);

const allFeatures = ['training', 'reports_export', 'ttx', 'case_studies', 'ctf'];
const featureLabels = {
  training: 'Pelatihan',
  reports_export: 'Ekspor laporan',
  ttx: 'Tabletop exercise',
  case_studies: 'Studi kasus',
  ctf: 'Capture the flag',
};

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

const openEdit = (Package) => {
  editingPlan.value = Package;
  form.value = {
    name: Package.name,
    price_monthly: Package.price_monthly,
    max_users: Package.max_users,
    features: Package.features ?? [],
    includes_all_modules: Package.includes_all_modules ?? false,
    module_ids: Package.modules?.map(m => m.id) ?? [],
  };
  showForm.value = true;
};

const submit = () => {
  if (editingPlan.value) {
    router.put(route('platform.packages.update', editingPlan.value.id), form.value, {
      onSuccess: () => { showForm.value = false; editingPlan.value = null; },
    });
  } else {
    router.post(route('platform.packages.store'), form.value, {
      onSuccess: () => { showForm.value = false; },
    });
  }
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');
</script>

<template>
  <Head title="Paket dan penagihan" />

  <AppLayout title="Paket dan penagihan">
    <div class="flex items-center justify-between mb-6">
      <p class="text-sm t-muted">Kelola paket langganan dan modul yang tersedia untuk setiap organisasi.</p>
      <button @click="openCreate" class="btn btn-primary">
        + Buat paket
      </button>
    </div>

    <div v-if="showForm" class="card p-6 mb-6">
      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-sm t-muted">Nama paket</label>
          <input v-model="form.name" type="text" required class="input mt-1 w-full" />
          <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
        </div>
        <div>
          <label class="text-sm t-muted">Harga (ribu rupiah per bulan)</label>
          <input v-model.number="form.price_monthly" type="number" min="0" class="input mt-1 w-full" />
        </div>
        <div>
          <label class="text-sm t-muted">Maks. pengguna</label>
          <input v-model.number="form.max_users" type="number" min="1" class="input mt-1 w-full" />
        </div>
        <div class="flex items-center gap-2 pt-6">
          <input v-model="form.includes_all_modules" type="checkbox" id="includes_all" class="rounded b-line" />
          <label for="includes_all" class="text-sm t-muted">Sertakan semua modul terbit</label>
        </div>
        <div v-if="!form.includes_all_modules" class="md:col-span-2">
          <label class="text-sm t-muted">Modul terpilih</label>
          <select v-model="form.module_ids" multiple class="input mt-1 w-full h-28">
            <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }}</option>
          </select>
          <p class="text-xs t-muted mt-1">Pilih satu atau beberapa modul yang tersedia untuk paket ini.</p>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm t-muted">Fitur paket</label>
          <div class="flex flex-wrap gap-3 mt-2">
            <label v-for="f in allFeatures" :key="f" class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" :value="f" v-model="form.features" class="rounded b-line" />
              {{ featureLabels[f] || f }}
            </label>
          </div>
        </div>
        <div class="md:col-span-2 flex justify-end gap-2">
          <button type="button" @click="showForm = false" class="btn btn-secondary">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan paket</button>
        </div>
      </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div v-for="Package in packages" :key="Package.id" class="card p-6 flex flex-col">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-semibold t-ink">{{ Package.name }}</h3>
          <span class="text-xs t-muted">{{ Package.subscriptions_count }} pelanggan</span>
        </div>
        <div class="text-2xl font-bold t-ink mb-1">{{ formatPrice(Package.price_monthly) }}</div>
        <div class="text-xs t-muted mb-2">Maks. {{ Package.max_users }} pengguna · {{ Package.includes_all_modules ? 'Semua modul terbit' : (Package.modules?.length ?? 0) + ' modul terpilih' }}</div>
        <div class="flex flex-wrap gap-1 mb-3">
          <span v-for="f in Package.features" :key="f" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium chip-brand">{{ featureLabels[f] || f }}</span>
        </div>
        <ul class="space-y-1 text-sm t-muted flex-1">
          <li v-for="m in Package.modules" :key="m.id" class="flex items-start gap-2 text-xs"><span class="t-muted">-</span>{{ m.title }}</li>
        </ul>
        <button @click="openEdit(Package)" class="btn btn-secondary mt-3 text-xs py-2">Edit paket</button>
      </div>
    </div>
  </AppLayout>
</template>
