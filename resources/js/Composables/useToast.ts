import { reactive } from 'vue';

export const toasts = reactive([]);
let nextId = 1;

function push(message, kind = 'info', ttl = 3500) {
  const id = nextId++;
  toasts.push({ id, message, kind });
  setTimeout(() => remove(id), ttl);
}

export function remove(id) {
  const idx = toasts.findIndex(t => t.id === id);
  if (idx >= 0) toasts.splice(idx, 1);
}

export function useToast() {
  return {
    toasts,
    success: (m) => push(m, 'success'),
    error:   (m) => push(m, 'error'),
    info:    (m) => push(m, 'info'),
    remove,
  };
}
