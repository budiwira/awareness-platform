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
      :aria-describedby="describedBy"
      class="base-input"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" :id="id+'-err'" class="base-input-error" role="alert">{{ error }}</p>
    <p v-if="hint" :id="id+'-hint'" class="base-input-hint">{{ hint }}</p>
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

const describedBy = computed(() => [
  props.error ? `${props.id}-err` : null,
  props.hint ? `${props.id}-hint` : null,
].filter(Boolean).join(' ') || undefined);
</script>

<style scoped>
.base-input-wrap { display: flex; flex-direction: column; gap: var(--sp-1); }
.base-input-label { font-size: .875rem; color: var(--muted); }
.base-input {
  padding: var(--sp-2) var(--sp-3);
  background: var(--input-bg); color: var(--ink);
  border: 1px solid var(--input-border); border-radius: var(--r-card);
  min-height: 44px;
  transition: border-color var(--dur-fast) var(--ease), box-shadow var(--dur-fast) var(--ease), opacity var(--dur-fast) var(--ease);
}
.base-input::placeholder { color: var(--muted); }
.base-input:focus { border-color: var(--brand); box-shadow: var(--glow); outline: none; }
.base-input:disabled { cursor: not-allowed; opacity: .6; }
.base-input[aria-invalid="true"] { border-color: var(--danger); box-shadow: 0 0 0 1px var(--danger-bg); }
.base-input-error { margin: 0; font-size: .8rem; color: var(--danger); }
.base-input-hint { margin: 0; font-size: .8rem; color: var(--muted); }
</style>
