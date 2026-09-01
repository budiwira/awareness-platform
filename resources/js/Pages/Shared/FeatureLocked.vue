<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
  title: String,
  message: String,
  cta: String,
});
</script>

<template>
  <AppLayout>
    <div class="min-h-[60vh] flex items-center justify-center px-4">
      <div class="max-w-md w-full text-center space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl badge-warn">
          <svg class="w-10 h-10 badge-warn" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl font-display font-bold t-ink">
            {{ title }}
          </h1>
          <p class="t-muted">
            {{ message }}
          </p>
        </div>

        <div class="flex flex-col gap-3 pt-2">
          <Link
            :href="route('dashboard')"
            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors duration-150"
          >
            Kembali ke Dashboard
          </Link>
          <p class="text-sm t-muted">
            {{ cta }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
