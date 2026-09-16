<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { BaseBadge } from '@/Components';

defineProps({
  campaigns: Array,
});

const statusVariant = (status) => ({
  draft: 'neutral',
  running: 'info',
  completed: 'success',
}[status] ?? 'neutral');
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

      <div v-if="campaigns.length === 0" class="card"><EmptyState title="Belum ada kampanye" message="Buat kampanye pertama Anda untuk memulai simulasi." /></div>

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
                <BaseBadge :variant="statusVariant(c.status)">
                  {{ c.status === 'draft' ? 'Draft' : c.status === 'running' ? 'Berjalan' : 'Selesai' }}
                </BaseBadge>
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
