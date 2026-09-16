<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseTextarea from '@/Components/BaseTextarea.vue';
import EmptyState from '@/Components/EmptyState.vue';

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
    <form @submit.prevent="submit" class="max-w-3xl space-y-6 fade-in">
      <div class="card p-6 space-y-5">
        <BaseInput v-model="form.title" label="Judul Kampanye" placeholder="Contoh: Test Kewaspadaan Phishing Q1 2026" required :error="form.errors.title" :disabled="form.processing" />
        <BaseInput v-model="form.sender_name" label="Nama Pengirim" placeholder="Contoh: IT Support" required :error="form.errors.sender_name" :disabled="form.processing" />
        <BaseInput v-model="form.subject" label="Subjek Email" placeholder="Contoh: URGENT: Verifikasi Akun Anda" required :error="form.errors.subject" :disabled="form.processing" />
        <BaseTextarea v-model="form.body_template" label="Isi Email" :rows="8" placeholder="Tulis isi simulasi dan sertakan {{link}}" hint="Gunakan {{link}} sebagai placeholder untuk tautan phishing." required :error="form.errors.body_template" :disabled="form.processing" />
      </div>

      <div class="card p-6 space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <label class="label mb-0">Target Simulasi</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input 
              v-model="selectAll" 
              type="checkbox" 
              @change="toggleSelectAll"
              class="h-5 w-5 rounded accent-[var(--brand)]"
              :disabled="form.processing"
            />
            <span class="t-muted">Pilih semua ({{ users.length }})</span>
          </label>
        </div>

        <div v-if="users.length" class="max-h-80 overflow-y-auto border border-divider rounded-lg">
          <label 
            v-for="u in users" 
            :key="u.id"
            class="flex items-center gap-3 p-3 hover:bg-surface-elevated transition cursor-pointer border-b border-divider last:border-0"
          >
            <input 
              v-model="form.target_user_ids" 
              :value="u.id" 
              type="checkbox"
              class="h-5 w-5 rounded accent-[var(--brand)]"
              :disabled="form.processing"
            />
            <div class="flex-1">
              <div class="font-medium t-ink">{{ u.name }}</div>
              <div class="text-sm t-muted">{{ u.email }}</div>
            </div>
          </label>
        </div>
        <EmptyState v-else title="Belum ada target" message="Belum ada learner aktif yang dapat dipilih untuk simulasi ini." />

        <div v-if="form.errors.target_user_ids" class="text-sm" style="color: var(--danger)" role="alert">{{ form.errors.target_user_ids }}</div>
        <div v-if="form.target_user_ids.length === 0" class="text-sm t-muted">
          Pilih minimal 1 target
        </div>
      </div>

      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
        <BaseButton type="submit" :loading="form.processing" :disabled="form.target_user_ids.length === 0">{{ form.processing ? 'Menyimpan...' : 'Simpan sebagai Draft' }}</BaseButton>
        <Link :href="route('tenant.phishing.index')" class="btn btn-ghost" :aria-disabled="form.processing" @click="form.processing && $event.preventDefault()">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
