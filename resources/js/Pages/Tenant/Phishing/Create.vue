<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  users: Array,
});

const form = useForm({
  title: '',
  sender_name: '',
  subject: '',
  body_template: '',
  target_user_ids: [],
});

const selectAll = ref(false);

const toggleSelectAll = () => {
  if (selectAll.value) {
    form.target_user_ids = props.users.map(u => u.id);
  } else {
    form.target_user_ids = [];
  }
};

const submit = () => {
  form.post(route('tenant.phishing.store'));
};
</script>

<template>
  <Head title="Buat Kampanye Phishing" />
  
  <AppLayout title="Buat Kampanye Phishing">
    <form @submit.prevent="submit" class="space-y-6 max-w-3xl">
      <div class="card p-6 space-y-5">
        <div>
          <label class="label">Judul Kampanye</label>
          <input 
            v-model="form.title" 
            type="text" 
            class="input" 
            placeholder="Contoh: Test Kewaspadaan Phishing Q1 2026"
            required
          />
          <div v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</div>
        </div>

        <div>
          <label class="label">Nama Pengirim</label>
          <input 
            v-model="form.sender_name" 
            type="text" 
            class="input" 
            placeholder="Contoh: IT Support"
            required
          />
          <div v-if="form.errors.sender_name" class="mt-1 text-sm text-red-600">{{ form.errors.sender_name }}</div>
        </div>

        <div>
          <label class="label">Subjek Email</label>
          <input 
            v-model="form.subject" 
            type="text" 
            class="input" 
            placeholder="Contoh: URGENT: Verifikasi Akun Anda"
            required
          />
          <div v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</div>
        </div>

        <div>
          <label class="label">Isi Email</label>
          <textarea 
            v-model="form.body_template" 
            class="input min-h-[180px]" 
            placeholder="Contoh:&#10;&#10;Halo,&#10;&#10;Akun Anda akan dinonaktifkan dalam 24 jam. Klik tautan berikut untuk verifikasi:&#10;{{link}}&#10;&#10;Terima kasih,&#10;Tim IT"
            required
          ></textarea>
          <div class="mt-2 text-sm t-muted">
            Gunakan <code class="px-1.5 py-0.5 rounded bg-surface-elevated font-mono text-xs">{{link}}</code> sebagai placeholder untuk tautan phishing
          </div>
          <div v-if="form.errors.body_template" class="mt-1 text-sm text-red-600">{{ form.errors.body_template }}</div>
        </div>
      </div>

      <div class="card p-6 space-y-4">
        <div class="flex items-center justify-between">
          <label class="label mb-0">Target Simulasi</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input 
              v-model="selectAll" 
              type="checkbox" 
              @change="toggleSelectAll"
              class="w-4 h-4 rounded border-gray-300"
            />
            <span class="t-muted">Pilih semua ({{ users.length }})</span>
          </label>
        </div>

        <div class="max-h-80 overflow-y-auto border border-divider rounded-lg">
          <label 
            v-for="u in users" 
            :key="u.id"
            class="flex items-center gap-3 p-3 hover:bg-surface-elevated transition cursor-pointer border-b border-divider last:border-0"
          >
            <input 
              v-model="form.target_user_ids" 
              :value="u.id" 
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300"
            />
            <div class="flex-1">
              <div class="font-medium t-ink">{{ u.name }}</div>
              <div class="text-sm t-muted">{{ u.email }}</div>
            </div>
          </label>
        </div>

        <div v-if="form.errors.target_user_ids" class="text-sm text-red-600">{{ form.errors.target_user_ids }}</div>
        <div v-if="form.target_user_ids.length === 0" class="text-sm t-muted">
          Pilih minimal 1 target
        </div>
      </div>

      <div class="flex items-center gap-3">
        <button 
          type="submit" 
          class="btn btn-primary"
          :disabled="form.processing || form.target_user_ids.length === 0"
        >
          <span v-if="form.processing">Menyimpan...</span>
          <span v-else>Simpan sebagai Draft</span>
        </button>
        <a 
          :href="route('tenant.phishing.index')" 
          class="btn btn-ghost"
        >
          Batal
        </a>
      </div>
    </form>
  </AppLayout>
</template>
