<template>
  <div class="base-input-wrap">
    <label v-if="label" :for="id" class="base-input-label">{{ label }}</label>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-invalid="!!error"
      :aria-describedby="error ? id+'-err' : undefined"
      class="base-input"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" :id="id+'-err'" class="base-input-error" role="alert">{{ error }}</p>
    <p v-else-if="hint" class="base-input-hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
  modelValue: String,
  label: String,
  type: { type: String, default: 'text' },
  placeholder: String,
  hint: String,
  error: String,
  id: { type: String, default: () => 'inp-' + Math.random().toString(36).slice(2, 8) },
  disabled: Boolean,
});
defineEmits(['update:modelValue']);
</script>

<style scoped>
.base-input-wrap { display: flex; flex-direction: column; gap: var(--sp-1); }
.base-input-label { font-size: .875rem; color: var(--color-muted); }
.base-input {
  padding: var(--sp-2) var(--sp-3);
  background: var(--color-surface); color: var(--color-text);
  border: 1px solid var(--color-border); border-radius: var(--r-md);
  min-height: 44px;
  transition: border-color var(--dur) var(--ease);
}
.base-input:focus { border-color: var(--color-primary); outline: none; }
.base-input[aria-invalid="true"] { border-color: var(--color-danger); }
.base-input-error { margin: 0; font-size: .8rem; color: var(--color-danger); }
.base-input-hint { margin: 0; font-size: .8rem; color: var(--color-muted); }
</style>
