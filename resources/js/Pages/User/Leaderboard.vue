<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  leaderboard: Array,
  currentUserPosition: Number,
  currentUserData: Object,
  totalParticipants: Number,
});

const getTierColor = (score) => {
  if (score >= 90) return 'var(--ok)';
  if (score >= 70) return 'var(--warn)';
  return 'var(--danger)';
};

const getTierLabel = (score) => {
  if (score >= 90) return 'Baik';
  if (score >= 70) return 'Cukup';
  return 'Perlu Perbaikan';
};
</script>

<template>
  <Head title="Leaderboard" />
  
  <AppLayout title="Leaderboard">
    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Current User Position -->
      <div v-if="currentUserData" class="card p-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="text-4xl font-display font-bold t-brand">
              #{{ currentUserPosition }}
            </div>
            <div>
              <div class="text-sm t-muted">Posisi Anda</div>
              <div class="font-semibold t-ink">{{ currentUserData.name }}</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-3xl font-display font-bold" :style="{ color: getTierColor(currentUserData.score) }">
              {{ currentUserData.score }}
            </div>
            <div class="text-xs t-muted">Awareness Score</div>
          </div>
        </div>
      </div>

      <!-- Leaderboard List -->
      <div class="card overflow-hidden">
        <div class="p-4 border-b border-divider">
          <h2 class="font-semibold t-ink">Top 20 Peringkat</h2>
          <p class="text-sm t-muted">{{ totalParticipants }} peserta</p>
        </div>

        <div class="divide-y divide-divider">
          <div
            v-for="(entry, index) in leaderboard"
            :key="entry.id"
            class="p-4 flex items-center gap-4 transition-colors duration-150"
            :class="entry.id === currentUserData?.id ? 'bg-brand/5' : 'hover:bg-surface-2'"
          >
            <!-- Rank -->
            <div 
              class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-display font-bold"
              :class="{
                'bg-gradient-to-br from-yellow-400 to-yellow-600 text-white': index === 0,
                'bg-gradient-to-br from-gray-300 to-gray-500 text-white': index === 1,
                'bg-gradient-to-br from-orange-400 to-orange-600 text-white': index === 2,
                'bg-surface-2 t-muted': index > 2,
              }"
            >
              {{ index + 1 }}
            </div>

            <!-- Avatar & Name -->
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div class="w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center flex-shrink-0 overflow-hidden">
                <img 
                  v-if="entry.avatar_path" 
                  :src="`/storage/${entry.avatar_path}`" 
                  :alt="entry.name"
                  class="w-full h-full object-cover"
                />
                <svg v-else class="w-5 h-5 t-muted" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="truncate">
                <div class="font-semibold t-ink truncate">{{ entry.name }}</div>
                <div class="text-xs t-muted">{{ getTierLabel(entry.score) }}</div>
              </div>
            </div>

            <!-- Score -->
            <div class="text-right flex-shrink-0">
              <div class="text-2xl font-display font-bold" :style="{ color: getTierColor(entry.score) }">
                {{ entry.score }}
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="leaderboard.length === 0" class="p-12 text-center">
          <div class="text-6xl mb-4">🏆</div>
          <h3 class="text-lg font-semibold t-ink mb-2">Belum Ada Data</h3>
          <p class="t-muted">Leaderboard akan tampil setelah user menyelesaikan aktivitas.</p>
        </div>
      </div>

      <!-- Info -->
      <div class="card p-4 bg-surface-2">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 t-muted flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <div class="text-sm t-muted">
            <p>Leaderboard menampilkan peringkat berdasarkan awareness score. Anda dapat opt-out dari leaderboard di halaman profil.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
