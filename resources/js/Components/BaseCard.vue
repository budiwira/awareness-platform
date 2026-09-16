<template>
  <div class="base-card" :class="{ 'base-card--interactive': interactive }">
    <header v-if="$slots.header || title" class="base-card-head">
      <h3 v-if="title" class="base-card-title">{{ title }}</h3>
      <slot name="header"></slot>
    </header>
    <div class="base-card-body"><slot /></div>
    <footer v-if="$slots.footer" class="base-card-foot"><slot name="footer"></slot></footer>
  </div>
</template>

<script setup>
defineProps({ title: String, interactive: Boolean });
</script>

<style scoped>
.base-card {
  background: var(--surface); border: 1px solid var(--line);
  border-radius: var(--r-card); padding: var(--sp-4);
  box-shadow: var(--shadow-sm);
}
.base-card--interactive { transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease), transform var(--dur) var(--ease); }
.base-card--interactive:hover { border-color: var(--brand); box-shadow: var(--shadow-md); transform: translateY(-1px); }
.base-card--interactive:focus-within { border-color: var(--brand); box-shadow: var(--glow); }
.base-card--interactive:active { transform: translateY(0); }
.base-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--sp-3); }
.base-card-title { margin: 0; color: var(--ink); font-size: 1rem; font-weight: 600; }
.base-card-body { color: var(--ink); }
.base-card-foot { margin-top: var(--sp-3); padding-top: var(--sp-3); border-top: 1px solid var(--line); }

@media (prefers-reduced-motion: reduce) {
  .base-card--interactive { transition: none; }
}
</style>
