<template>
  <div class="base-alert" :class="`base-alert--${variant}`" :role="variant === 'danger' ? 'alert' : 'status'">
    <div v-if="title" class="base-alert-title">{{ title }}</div>
    <div><slot /></div>
  </div>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'info' },
  title: String,
});
</script>

<style scoped>
.base-alert { padding: var(--sp-3) var(--sp-4); border: 1px solid; border-radius: var(--r-lg); color: var(--ink); font-size: .875rem; }
.base-alert-title { margin-bottom: var(--sp-1); font-weight: 700; }
.base-alert--info { background: var(--brand-soft); border-color: var(--brand); }
.base-alert--success { background: var(--ok-bg); border-color: var(--ok); color: var(--ok); }
.base-alert--warning { background: var(--warn-bg); border-color: var(--warn); color: var(--warn); }
.base-alert--danger { background: var(--danger-bg); border-color: var(--danger); color: var(--danger); }
</style>
