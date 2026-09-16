<template>
  <Teleport to="body">
    <div class="toast-region" role="region" aria-live="polite" aria-label="Notifikasi">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          :class="['toast', 'toast--'+t.kind]"
          role="status"
        >
          <span class="toast-message">{{ t.message }}</span>
          <button type="button" class="toast-close" aria-label="Tutup notifikasi" @click="remove(t.id)">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { toasts, remove } from '../Composables/useToast';
</script>

<style scoped>
.toast-region {
  position: fixed; top: var(--sp-4); right: var(--sp-4);
  display: flex; flex-direction: column; gap: var(--sp-2);
  z-index: var(--z-toast); max-width: 360px;
}
.toast {
  display: flex; align-items: center; gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  background: var(--surface); color: var(--ink);
  border: 1px solid var(--line); border-radius: var(--r-lg);
  box-shadow: var(--shadow-md);
}
.toast--success { background: var(--ok-bg); border-color: var(--ok); }
.toast--error   { background: var(--danger-bg); border-color: var(--danger); }
.toast--info    { background: var(--brand-soft); border-color: var(--brand); }
.toast-message { flex: 1; }
.toast-close { display: inline-flex; width: 44px; height: 44px; flex: 0 0 44px; align-items: center; justify-content: center; border: 0; border-radius: var(--r-md); background: transparent; color: var(--ink); cursor: pointer; }
.toast-close:hover { background: var(--surface-2); }
.toast-close:active { transform: scale(.96); }
.toast-close svg { width: 18px; height: 18px; }
.toast-enter-active, .toast-leave-active { transition: all var(--dur) var(--ease); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(20px); }

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active, .toast-leave-active { transition: none; }
}
</style>
