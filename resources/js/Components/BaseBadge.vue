<template>
  <span class="base-badge" :class="[`base-badge--${variant}`, `base-badge--${size}`]">
    <slot />
  </span>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'neutral' },
  size: { type: String, default: 'md' },
});
</script>

<style scoped>
.base-badge { display: inline-flex; align-items: center; border: 1px solid transparent; border-radius: var(--r-full); font-weight: 600; line-height: 1.25; white-space: nowrap; }
.base-badge--sm { padding: .125rem .5rem; font-size: .6875rem; }
.base-badge--md { padding: .2rem .625rem; font-size: .75rem; }
.base-badge--neutral { background: var(--surface-2); border-color: var(--line); color: var(--muted); }
.base-badge--brand, .base-badge--info { background: var(--brand-soft); border-color: var(--brand); color: var(--ink); }
.base-badge--success { background: var(--ok-bg); border-color: var(--ok); color: var(--ok); }
.base-badge--warning { background: var(--warn-bg); border-color: var(--warn); color: var(--warn); }
.base-badge--danger { background: var(--danger-bg); border-color: var(--danger); color: var(--danger); }
</style>
