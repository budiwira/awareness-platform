<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ packages: Array, modules: Array });
const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const editingPlan = ref(null);
const submitting = ref(false);

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
  if (submitting.value) return;
  submitting.value = true;
  if (editingPlan.value) {
    router.put(route('platform.packages.update', editingPlan.value.id), form.value, {
      onSuccess: () => { showForm.value = false; editingPlan.value = null; },
      onFinish: () => { submitting.value = false; },
    });
  } else {
    router.post(route('platform.packages.store'), form.value, {
      onSuccess: () => { showForm.value = false; },
      onFinish: () => { submitting.value = false; },
    });
  }
};

const formatPrice = (p) => (p === 0 ? 'Gratis' : 'Rp ' + (p * 1000).toLocaleString('id-ID') + '/bln');
</script>

<template>
  <Head title="Paket dan penagihan" />

  <AppLayout title="Paket dan penagihan">
    <div class="fade-in space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm t-muted">Kelola paket langganan dan modul yang tersedia untuk setiap organisasi.</p>
          <p class="mt-1 text-xs t-muted">{{ packages.length }} paket tersedia</p>
        </div>
        <BaseButton @click="openCreate" aria-label="Buat paket baru">Buat paket</BaseButton>
      </div>

      <BaseAlert v-if="Object.keys(errors).length" variant="danger" title="Paket belum tersimpan">
        Periksa kembali data yang ditandai lalu coba lagi.
      </BaseAlert>
    </div>

    <div v-if="showForm" class="card p-4 sm:p-6">
      <div class="mb-5 flex items-start justify-between gap-4">
        <div>
          <h2 class="font-display text-lg t-ink">{{ editingPlan ? 'Edit paket' : 'Buat paket baru' }}</h2>
          <p class="mt-1 text-sm t-muted">Atur harga, kapasitas, fitur, dan modul yang tersedia.</p>
        </div>
        <BaseButton variant="ghost" size="sm" @click="showForm = false" aria-label="Tutup formulir">Tutup</BaseButton>
      </div>
      <form @submit.prevent="submit" class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <BaseInput v-model="form.name" label="Nama paket" :error="errors.name" required />
        <BaseInput v-model.number="form.price_monthly" type="number" min="0" label="Harga (ribu rupiah per bulan)" required />
        <BaseInput v-model.number="form.max_users" type="number" min="1" label="Maks. pengguna" />
        <label class="flex min-h-[44px] items-center gap-3 self-end rounded-xl border b-line px-3 text-sm t-muted">
          <input v-model="form.includes_all_modules" type="checkbox" id="includes_all" class="rounded b-line" />
          <span>Sertakan semua modul terbit</span>
        </label>
        <div v-if="!form.includes_all_modules" class="md:col-span-2">
          <label for="module_ids" class="text-sm t-muted">Modul terpilih</label>
          <select id="module_ids" v-model="form.module_ids" multiple class="input mt-1 h-32 w-full" aria-describedby="module-hint">
            <option v-for="mod in modules" :key="mod.id" :value="mod.id">{{ mod.title }}</option>
          </select>
          <p id="module-hint" class="mt-1 text-xs t-muted">Pilih satu atau beberapa modul yang tersedia untuk paket ini.</p>
        </div>
        <fieldset class="md:col-span-2">
          <legend class="text-sm t-muted">Fitur paket</legend>
          <div class="mt-2 flex flex-wrap gap-2">
            <label v-for="f in allFeatures" :key="f" class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border b-line px-3 text-sm t-muted">
              <input type="checkbox" :value="f" v-model="form.features" class="rounded b-line" />
              {{ featureLabels[f] || f }}
            </label>
          </div>
        </fieldset>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end md:col-span-2">
          <BaseButton type="button" variant="secondary" @click="showForm = false">Batal</BaseButton>
          <BaseButton type="submit" :loading="submitting" :disabled="submitting">{{ submitting ? 'Menyimpan...' : 'Simpan paket' }}</BaseButton>
        </div>
      </form>
    </div>

    <div v-if="packages.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="Package in packages" :key="Package.id" class="card flex flex-col p-5 sm:p-6">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h3 class="font-display text-lg t-ink">{{ Package.name }}</h3>
            <p class="mt-1 text-xs t-muted">{{ Package.subscriptions_count }} pelanggan aktif</p>
          </div>
          <BaseBadge :variant="Package.is_active ? 'success' : 'neutral'">{{ Package.is_active ? 'Aktif' : 'Nonaktif' }}</BaseBadge>
        </div>
        <div class="mb-1 text-2xl font-bold t-ink">{{ formatPrice(Package.price_monthly) }}</div>
        <div class="mb-4 text-sm t-muted">Maks. {{ Package.max_users ?? 'tanpa batas' }} pengguna</div>
        <div class="mb-4 flex flex-wrap gap-2">
          <BaseBadge v-for="f in Package.features" :key="f" variant="info" size="sm">{{ featureLabels[f] || f }}</BaseBadge>
        </div>
        <div class="mb-5 flex-1 rounded-xl bg-surface2 p-3">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide t-muted">{{ Package.includes_all_modules ? 'Semua modul terbit' : `${Package.modules?.length ?? 0} modul terpilih` }}</p>
          <ul class="space-y-1 text-sm t-muted">
            <li v-for="m in Package.modules" :key="m.id" class="truncate" :title="m.title">{{ m.title }}</li>
            <li v-if="!Package.includes_all_modules && !Package.modules?.length" class="text-xs">Belum ada modul dipilih.</li>
          </ul>
        </div>
        <BaseButton variant="secondary" @click="openEdit(Package)" :aria-label="`Edit paket ${Package.name}`">Edit paket</BaseButton>
      </div>
    </div>
    <EmptyState v-else title="Belum ada paket" message="Buat paket pertama untuk mulai mengatur pilihan langganan organisasi." />
  </AppLayout>
</template>
