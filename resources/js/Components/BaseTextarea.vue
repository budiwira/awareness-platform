<template>
  <div class="base-field">
    <label v-if="label" :for="id" class="base-label">{{ label }}</label>
    <textarea
      :id="id"
      :value="modelValue"
      :rows="rows"
      :maxlength="maxlength"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :aria-invalid="!!error"
      :aria-describedby="describedBy"
      class="base-control"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" :id="id+'-err'" class="base-error" role="alert">{{ error }}</p>
    <p v-if="hint" :id="id+'-hint'" class="base-hint">{{ hint }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: String,
  label: String,
  hint: String,
  error: String,
  id: { type: String, default: () => 'txt-' + Math.random().toString(36).slice(2, 8) },
  rows: { type: Number, default: 3 },
  maxlength: Number,
  placeholder: String,
  disabled: Boolean,
  required: Boolean,
});

defineEmits(['update:modelValue']);

const describedBy = computed(() => [
  props.error ? `${props.id}-err` : null,
  props.hint ? `${props.id}-hint` : null,
].filter(Boolean).join(' ') || undefined);
</script>

<style scoped>
.base-field { display: flex; flex-direction: column; gap: var(--sp-1); }
.base-label { font-size: .875rem; color: var(--muted); }
.base-control { width: 100%; min-height: 88px; padding: var(--sp-2) var(--sp-3); resize: vertical; border: 1px solid var(--input-border); border-radius: var(--r-card); background: var(--input-bg); color: var(--ink); transition: border-color var(--dur-fast) var(--ease), box-shadow var(--dur-fast) var(--ease), opacity var(--dur-fast) var(--ease); }
.base-control::placeholder { color: var(--muted); }
.base-control:focus { outline: none; border-color: var(--brand); box-shadow: var(--glow); }
.base-control:disabled { cursor: not-allowed; opacity: .6; }
.base-control[aria-invalid="true"] { border-color: var(--danger); box-shadow: 0 0 0 1px var(--danger-bg); }
.base-error { margin: 0; color: var(--danger); font-size: .8rem; }
.base-hint { margin: 0; color: var(--muted); font-size: .8rem; }
</style>
