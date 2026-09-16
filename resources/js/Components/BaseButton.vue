<template>
  <button
    :class="classes"
    :disabled="disabled || loading"
    :aria-busy="loading"
    :type="type"
  >
    <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>
    <span><slot /></span>
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: { type: String, default: 'primary' },   // primary | secondary | ghost | danger
  size:    { type: String, default: 'md' },         // sm | md | lg
  loading: Boolean,
  disabled: Boolean,
  type:    { type: String, default: 'button' },
});

const classes = computed(() => [
  'base-btn',
  `base-btn--${props.variant}`,
  `base-btn--${props.size}`,
  { 'is-loading': props.loading, 'is-disabled': props.disabled },
]);
</script>

<style scoped>
.base-btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: var(--sp-2); padding: var(--sp-2) var(--sp-4);
  min-height: 44px;                          /* touch target AA */
  border: 1px solid transparent;
  border-radius: var(--r-full);
  font-weight: 500; cursor: pointer;
  transition: background var(--dur) var(--ease), border-color var(--dur) var(--ease), transform var(--dur-fast) var(--ease);
}
.base-btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.base-btn:active:not(:disabled) { transform: translateY(1px); }
.base-btn--primary { background: var(--brand); color: var(--white); }
.base-btn--primary:hover:not(:disabled) { background: var(--brand-strong); box-shadow: var(--glow); }
.base-btn--secondary { background: var(--surface); color: var(--ink); border-color: var(--line); }
.base-btn--secondary:hover:not(:disabled) { background: var(--surface-2); border-color: var(--brand); }
.base-btn--ghost { background: transparent; color: var(--muted); }
.base-btn--ghost:hover:not(:disabled) { background: var(--surface-2); color: var(--ink); }
.base-btn--danger { background: var(--danger); color: var(--white); }
.base-btn--danger:hover:not(:disabled) { box-shadow: 0 0 0 3px var(--danger-bg); }
.base-btn--sm { min-height: 44px; padding: var(--sp-1) var(--sp-3); font-size: .875rem; }
.base-btn--lg { min-height: 52px; padding: var(--sp-3) var(--sp-5); }
.base-btn.is-disabled, .base-btn:disabled { opacity: .55; cursor: not-allowed; pointer-events: none; }
.btn-spinner {
  width: 14px; height: 14px; border: 2px solid currentColor;
  border-right-color: transparent; border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (prefers-reduced-motion: reduce) {
  .base-btn, .btn-spinner { animation: none; transition: none; }
}
</style>
