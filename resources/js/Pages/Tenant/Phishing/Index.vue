<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  campaigns: Array,
});
</script>

<template>
  <Head title="Simulasi Phishing" />
  
  <AppLayout title="Simulasi Phishing">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <p class="t-muted">
          Kampanye simulasi phishing untuk melatih kewaspadaan tim Anda
        </p>
        <Link 
          :href="route('tenant.phishing.create')" 
          class="btn btn-primary"
        >
          <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Buat Kampanye
        </Link>
      </div>

      <div v-if="campaigns.length === 0" class="card p-12 text-center space-y-3">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-surface-elevated">
          <svg class="w-8 h-8 t-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <div class="font-semibold t-ink">Belum ada kampanye</div>
          <div class="text-sm t-muted">Buat kampanye pertama Anda untuk memulai simulasi</div>
        </div>
      </div>

      <div v-else class="grid gap-4">
        <Link 
          v-for="c in campaigns" 
          :key="c.id" 
          :href="route('tenant.phishing.show', c.id)"
          class="card p-6 hover:shadow-lg transition-all duration-150"
        >
          <div class="flex items-start justify-between">
            <div class="space-y-2 flex-1">
              <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold t-ink">{{ c.title }}</h3>
                <span 
                  class="px-2.5 py-0.5 rounded text-xs font-medium"
                  :class="{
                    'badge-default': c.status === 'draft',
                    'badge-info': c.status === 'running',
                    'badge-ok': c.status === 'completed'
                  }"
                >
                  {{ c.status === 'draft' ? 'Draft' : c.status === 'running' ? 'Berjalan' : 'Selesai' }}
                </span>
              </div>
              <div class="text-sm t-muted space-y-1">
                <div>Pengirim: {{ c.sender_name }}</div>
                <div>Subjek: {{ c.subject }}</div>
                <div>{{ c.targets_count }} target</div>
              </div>
              <div class="text-xs t-muted">
                Dibuat oleh {{ c.creator.name }} • {{ new Date(c.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
              </div>
            </div>
            <svg class="w-5 h-5 t-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
