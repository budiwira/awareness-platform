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
          <span>{{ t.message }}</span>
          <button class="toast-close" aria-label="Tutup" @click="remove(t.id)">×</button>
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
  background: var(--color-surface); color: var(--color-text);
  border: 1px solid var(--color-border); border-radius: var(--r-md);
  box-shadow: var(--shadow-md);
}
.toast--success { border-left: 3px solid var(--color-success); }
.toast--error   { border-left: 3px solid var(--color-danger); }
.toast--info    { border-left: 3px solid var(--color-primary); }
.toast-close { background: transparent; border: 0; color: var(--color-muted); cursor: pointer; font-size: 1.25rem; padding: 0 var(--sp-1); }
.toast-enter-active, .toast-leave-active { transition: all var(--dur) var(--ease); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(20px); }
</style>
