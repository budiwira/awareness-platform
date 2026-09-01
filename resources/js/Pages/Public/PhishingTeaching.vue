<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
  campaignTitle: String,
  remedialAssigned: Boolean,
});
</script>

<template>
  <Head title="Pelatihan Keamanan" />
  
  <div class="min-h-screen bg-app flex items-center justify-center px-4 py-12">
    <div class="max-w-2xl w-full space-y-8">
      <div class="text-center space-y-4">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-red-100 dark:bg-red-900/30">
          <svg class="w-10 h-10 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1 class="text-3xl font-display font-bold t-ink">
          Ini Adalah Simulasi Keamanan
        </h1>
        <p class="text-lg t-muted">
          Anda baru saja mengklik tautan dalam email simulasi phishing dari organisasi Anda
        </p>
      </div>

      <div class="card p-8 space-y-6">
        <div>
          <h2 class="text-xl font-semibold t-ink mb-4">Tanda-tanda Phishing pada Email Ini:</h2>
          <div class="space-y-4">
            <div class="flex gap-4">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <span class="text-red-600 dark:text-red-400 font-bold">1</span>
              </div>
              <div class="flex-1">
                <div class="font-medium t-ink mb-1">Pengirim Mencurigakan</div>
                <div class="text-sm t-muted">Email phishing sering menyamar sebagai pengirim resmi, tapi alamat email sebenarnya berbeda</div>
              </div>
            </div>
            
            <div class="flex gap-4">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <span class="text-red-600 dark:text-red-400 font-bold">2</span>
              </div>
              <div class="flex-1">
                <div class="font-medium t-ink mb-1">Urgensi Berlebihan</div>
                <div class="text-sm t-muted">Pesan yang memaksa Anda bertindak cepat (akun diblokir, verifikasi segera) adalah taktik umum phishing</div>
              </div>
            </div>
            
            <div class="flex gap-4">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <span class="text-red-600 dark:text-red-400 font-bold">3</span>
              </div>
              <div class="flex-1">
                <div class="font-medium t-ink mb-1">Tautan Mencurigakan</div>
                <div class="text-sm t-muted">Selalu periksa URL sebelum diklik. Arahkan kursor ke tautan (tanpa klik) untuk melihat tujuan sebenarnya</div>
              </div>
            </div>
          </div>
        </div>

        <div class="border-t border-divider pt-6">
          <h3 class="font-semibold t-ink mb-3">Tips Melindungi Diri:</h3>
          <ul class="space-y-2 text-sm t-muted">
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Verifikasi pengirim melalui saluran resmi sebelum mengklik tautan</span>
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Jangan pernah memberikan password atau data sensitif melalui email</span>
            </li>
            <li class="flex items-start gap-2">
              <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Laporkan email mencurigakan ke tim IT segera</span>
            </li>
          </ul>
        </div>
      </div>

      <div v-if="remedialAssigned" class="card p-6 bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-800">
        <div class="flex gap-3">
          <div class="flex-shrink-0">
            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Pelatihan Tambahan Tersedia</h3>
            <p class="text-sm text-blue-800 dark:text-blue-200">
              Kami telah menugaskan pelatihan tambahan untuk membantu Anda mengenali phishing. Silakan cek dashboard training Anda.
            </p>
          </div>
        </div>
      </div>

      <div class="text-center space-y-3">
        <button 
          @click="() => window.close()"
          class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors duration-150"
        >
          Saya Mengerti
        </button>
        <p class="text-sm t-muted">
          Simulasi ini merupakan bagian dari program pelatihan keamanan organisasi Anda
        </p>
      </div>
    </div>
  </div>
</template>
