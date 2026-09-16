<template>
  <div class="base-table-container">
    <div class="base-table-scroll">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.base-table-container { max-width: 100%; overflow: hidden; border: 1px solid var(--line); border-radius: var(--r-card); background: var(--surface); }
.base-table-scroll { max-width: 100%; overflow-x: auto; }
.base-table-scroll :deep(table) { min-width: 720px; }
.base-table-scroll :deep(thead) { color: var(--muted); }
.base-table-scroll :deep(th) { font-weight: 500; }
.base-table-scroll :deep(tr) { border-color: var(--line); }
.base-table-scroll :deep(tbody tr) { transition: background-color var(--dur-fast) var(--ease); }
.base-table-scroll :deep(tbody tr:hover) { background: var(--surface-2); }
</style>
