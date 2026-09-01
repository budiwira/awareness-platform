<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  campaign: Object,
  targets: Array,
  stats: Object,
});

const sendForm = useForm({});

const sendCampaign = () => {
  if (!confirm('Kirim email simulasi ke semua target sekarang?')) return;
  sendForm.post(route('tenant.phishing.send', props.campaign.id));
};

const formatDate = (iso) => {
  if (!iso) return '-';
  const d = new Date(iso);
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <Head :title="campaign.title" />
  
  <AppLayout :title="campaign.title">
    <div class="space-y-6">
      <div class="flex items-start justify-between">
        <div>
          <a 
            :href="route('tenant.phishing.index')" 
            class="inline-flex items-center text-sm t-muted hover:t-ink transition mb-3"
          >
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali
          </a>
          <div class="flex items-center gap-3">
            <span 
              class="px-2.5 py-0.5 rounded text-xs font-medium"
              :class="{
                'badge-default': campaign.status === 'draft',
                'badge-info': campaign.status === 'running',
                'badge-ok': campaign.status === 'completed'
              }"
            >
              {{ campaign.status === 'draft' ? 'Draft' : campaign.status === 'running' ? 'Berjalan' : 'Selesai' }}
            </span>
          </div>
        </div>
        <button 
          v-if="campaign.status === 'draft'" 
          @click="sendCampaign"
          class="btn btn-primary"
          :disabled="sendForm.processing"
        >
          <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          <span v-if="sendForm.processing">Mengirim...</span>
          <span v-else>Kirim Sekarang</span>
        </button>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="card p-6 space-y-2">
          <div class="text-sm t-muted">Total Target</div>
          <div class="text-3xl font-display font-bold t-ink">{{ stats.total }}</div>
        </div>
        <div class="card p-6 space-y-2">
          <div class="text-sm t-muted">Diklik</div>
          <div class="text-3xl font-display font-bold t-ink">{{ stats.clicked }}</div>
        </div>
        <div class="card p-6 space-y-2">
          <div class="text-sm t-muted">Click Rate</div>
          <div class="text-3xl font-display font-bold" :style="{ color: stats.click_rate > 30 ? 'var(--danger)' : stats.click_rate > 10 ? 'var(--warn)' : 'var(--ok)' }">
            {{ stats.click_rate }}%
          </div>
        </div>
      </div>

      <div class="card p-6 space-y-4">
        <h2 class="text-lg font-semibold t-ink">Preview Email</h2>
        <div class="space-y-3 p-4 rounded-lg bg-surface-elevated">
          <div class="text-sm">
            <span class="t-muted">Dari:</span> 
            <span class="t-ink font-medium ml-2">{{ campaign.sender_name }}</span>
          </div>
          <div class="text-sm">
            <span class="t-muted">Subjek:</span> 
            <span class="t-ink font-medium ml-2">{{ campaign.subject }}</span>
          </div>
          <div class="border-t border-divider pt-3 text-sm t-ink whitespace-pre-wrap font-mono">{{ campaign.body_template }}</div>
        </div>
      </div>

      <div class="card p-6 space-y-4">
        <h2 class="text-lg font-semibold t-ink">Target ({{ targets.length }})</h2>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="border-b border-divider">
              <tr class="text-left text-sm t-muted">
                <th class="pb-3 font-medium">Nama</th>
                <th class="pb-3 font-medium">Email</th>
                <th class="pb-3 font-medium text-center">Status</th>
                <th class="pb-3 font-medium text-right">Waktu Klik</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-divider">
              <tr v-for="t in targets" :key="t.id" class="text-sm">
                <td class="py-3 t-ink">{{ t.user_name }}</td>
                <td class="py-3 t-muted">{{ t.user_email }}</td>
                <td class="py-3 text-center">
                  <span 
                    class="px-2 py-0.5 rounded text-xs font-medium"
                    :class="t.status === 'clicked' ? 'badge-danger' : 'badge-default'"
                  >
                    {{ t.status === 'clicked' ? 'Diklik' : 'Terkirim' }}
                  </span>
                </td>
                <td class="py-3 text-right t-muted">{{ formatDate(t.clicked_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
