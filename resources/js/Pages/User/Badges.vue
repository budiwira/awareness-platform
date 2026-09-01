<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  badges: Object,
  totalBadges: Number,
  earnedCount: Number,
});

const categoryLabels = {
  completion: 'Penyelesaian',
  quiz: 'Quiz',
  phishing: 'Phishing',
  streak: 'Streak',
  achievement: 'Pencapaian',
};

const progress = computed(() => {
  if (props.totalBadges === 0) return 0;
  return Math.round((props.earnedCount / props.totalBadges) * 100);
});

const formatDate = (dateString) => {
  if (!dateString) return '';
  const d = new Date(dateString);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
};
</script>

<template>
  <Head title="Badge Saya" />
  
  <AppLayout title="Badge Saya">
    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Progress Card -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-lg font-semibold t-ink">Koleksi Badge</h2>
            <p class="text-sm t-muted">{{ earnedCount }} dari {{ totalBadges }} badge terkumpul</p>
          </div>
          <div class="text-4xl font-display font-bold t-brand">
            {{ progress }}%
          </div>
        </div>
        <div class="h-2 bg-surface-2 rounded-full overflow-hidden">
          <div 
            class="h-full bg-brand transition-all duration-300"
            :style="{ width: progress + '%' }"
          ></div>
        </div>
      </div>

      <!-- Badge Grid by Category -->
      <div v-for="(categoryBadges, category) in badges" :key="category" class="space-y-3">
        <h3 class="text-base font-semibold t-ink">{{ categoryLabels[category] || category }}</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div
            v-for="badge in categoryBadges"
            :key="badge.id"
            class="card p-4 flex flex-col items-center text-center transition-all duration-200"
            :class="badge.earned ? 'bg-surface border-brand/20' : 'bg-surface-2 opacity-50'"
          >
            <div 
              class="text-5xl mb-3 transition-transform duration-200"
              :class="badge.earned ? 'scale-100' : 'grayscale scale-90'"
            >
              {{ badge.icon }}
            </div>
            <h4 class="font-semibold text-sm t-ink mb-1">{{ badge.name }}</h4>
            <p class="text-xs t-muted mb-2">{{ badge.description }}</p>
            
            <div v-if="badge.earned" class="mt-auto">
              <div class="inline-flex items-center gap-1 text-xs t-ok px-2 py-1 bg-ok/10 rounded">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>{{ formatDate(badge.earned_at) }}</span>
              </div>
            </div>
            <div v-else class="mt-auto text-xs t-muted italic">
              Belum didapat
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="earnedCount === 0" class="card p-12 text-center">
        <div class="text-6xl mb-4">🎯</div>
        <h3 class="text-lg font-semibold t-ink mb-2">Belum Ada Badge</h3>
        <p class="t-muted">Selesaikan modul training dan quiz untuk mendapatkan badge pertama Anda!</p>
      </div>
    </div>
  </AppLayout>
</template>
