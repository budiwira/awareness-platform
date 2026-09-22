<script setup>
import { computed, ref, watch } from 'vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseTextarea from '@/Components/BaseTextarea.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    /** @type {'active'|'locked'|'pending'} */
    injectStatus: { type: String, required: true },
    /** @type {{ id: number, decision: string, rationale: string|null, owner: string|null, immediate_actions: string|null, escalation: string|null, unknowns: string|null, notes: string|null, revision: number, submitted_at: string, locked_at: string|null } | null} */
    response: { type: Object, default: null },
    injectId: { type: Number, required: true },
    saving: { type: Boolean, default: false },
});

const emit = defineEmits(['save', 'discard']);

// --- Draft state ---
const draft = ref(emptyDraft());
const serverSnapshot = ref(null);
const currentRevision = ref(null);
const isCreate = computed(() => !serverSnapshot.value);
const isLocked = computed(() => props.injectStatus === 'locked');
const isActive = computed(() => props.injectStatus === 'active');

// --- Server conflict state ---
const conflictError = ref(null);     // 409 conflict message
const validationErrors = ref(null);  // 422 validation error bag
const generalError = ref(null);      // other errors (403, etc.)

function emptyDraft() {
    return {
        decision: '',
        rationale: '',
        owner: '',
        immediate_actions: '',
        escalation: '',
        unknowns: '',
        notes: '',
    };
}

// --- Sync draft with response prop ---
watch(
    () => props.response,
    (resp) => {
        serverSnapshot.value = resp ? { ...resp } : null;
        currentRevision.value = resp?.revision ?? null;
        conflictError.value = null;
        validationErrors.value = null;
        generalError.value = null;

        if (resp) {
            draft.value = {
                decision: resp.decision ?? '',
                rationale: resp.rationale ?? '',
                owner: resp.owner ?? '',
                immediate_actions: resp.immediate_actions ?? '',
                escalation: resp.escalation ?? '',
                unknowns: resp.unknowns ?? '',
                notes: resp.notes ?? '',
            };
        } else {
            draft.value = emptyDraft();
        }
    },
    { immediate: true },
);

// --- Dirty state ---
const isDirty = computed(() => {
    if (!serverSnapshot.value) {
        // For new response, dirty if decision is non-empty
        return draft.value.decision.trim() !== '';
    }
    const fields = ['decision', 'rationale', 'owner', 'immediate_actions', 'escalation', 'unknowns', 'notes'];
    return fields.some((f) => {
        const draftVal = (draft.value[f] ?? '').trim();
        const serverVal = (serverSnapshot.value[f] ?? '').trim();
        return draftVal !== serverVal;
    });
});

// --- Save enabled ---
const decisionNotBlank = computed(() => draft.value.decision.trim().length > 0);
const canSave = computed(() => {
    if (props.saving) return false;
    if (isLocked.value) return false;
    if (!isActive.value) return false;
    if (!decisionNotBlank.value) return false;
    if (isCreate.value) return true;
    return isDirty.value;
});

// --- Save handler ---
const handleSave = () => {
    if (!canSave.value) return;
    emit('save', {
        isCreate: isCreate.value,
        draft: { ...draft.value },
        expectedRevision: currentRevision.value,
    });
};

// --- Field errors from 422 ---
const fieldErrors = computed(() => {
    if (!validationErrors.value) return {};
    const errs = {};
    if (validationErrors.value.session) errs.session = validationErrors.value.session;
    if (validationErrors.value.inject) errs.inject = validationErrors.value.inject;
    if (validationErrors.value.response) errs.response = validationErrors.value.response;
    if (validationErrors.value.decision) errs.decision = validationErrors.value.decision;
    return errs;
});

// --- Expose methods for parent to set error states ---
const setConflictError = (msg) => {
    conflictError.value = msg;
    validationErrors.value = null;
    generalError.value = null;
};

const setValidationError = (bag) => {
    validationErrors.value = bag;
    conflictError.value = null;
    generalError.value = null;
};

const setGeneralError = (msg) => {
    generalError.value = msg;
    conflictError.value = null;
    validationErrors.value = null;
};

const clearErrors = () => {
    conflictError.value = null;
    validationErrors.value = null;
    generalError.value = null;
};

const resetDraft = () => {
    serverSnapshot.value = null;
    currentRevision.value = null;
    draft.value = emptyDraft();
    conflictError.value = null;
    validationErrors.value = null;
    generalError.value = null;
};

defineExpose({
    isDirty,
    serverSnapshot,
    currentRevision,
    setConflictError,
    setValidationError,
    setGeneralError,
    clearErrors,
    resetDraft,
});
</script>

<template>
    <div class="response-editor" role="form" aria-label="Response Tim">
        <!-- Conflict alert (409 stale update) -->
        <BaseAlert
            v-if="conflictError"
            variant="warning"
            title="Konflik Versi"
            class="mb-4"
        >
            <p>{{ conflictError }}</p>
            <p class="mt-1">Perubahan lokal Anda belum dikirim.</p>
            <BaseButton
                variant="secondary"
                size="sm"
                class="mt-3"
                @click="$emit('discard', { reason: 'conflict-load-latest' })"
            >
                Muat Versi Terbaru
            </BaseButton>
        </BaseAlert>

        <!-- General error (403, lifecycle, etc.) -->
        <BaseAlert
            v-if="generalError"
            variant="danger"
            title="Gagal"
            class="mb-4"
        >
            <p>{{ generalError }}</p>
        </BaseAlert>

        <!-- Validation error (422 lifecycle: inject no longer active, etc.) -->
        <BaseAlert
            v-if="fieldErrors.session"
            variant="danger"
            class="mb-4"
        >
            {{ fieldErrors.session }}
        </BaseAlert>
        <BaseAlert
            v-if="fieldErrors.inject"
            variant="danger"
            class="mb-4"
        >
            {{ fieldErrors.inject }}
        </BaseAlert>
        <BaseAlert
            v-if="fieldErrors.response"
            variant="danger"
            class="mb-4"
        >
            {{ fieldErrors.response }}
        </BaseAlert>

        <!-- Decision (required) -->
        <div class="response-field">
            <BaseInput
                v-model="draft.decision"
                label="Keputusan *"
                placeholder="Tuliskan keputusan tim untuk injeksi ini"
                :error="fieldErrors.decision"
                :disabled="isLocked"
                required
            />
            <p v-if="!isLocked && !decisionNotBlank" class="response-hint">
                Keputusan wajib diisi sebelum menyimpan.
            </p>
        </div>

        <!-- Rationale -->
        <div class="response-field">
            <BaseTextarea
                v-model="draft.rationale"
                label="Rationale"
                placeholder="Jelaskan alasan di balik keputusan ini"
                :rows="3"
                :disabled="isLocked"
            />
        </div>

        <!-- Owner -->
        <div class="response-field">
            <BaseInput
                v-model="draft.owner"
                label="Penanggung Jawab"
                placeholder="Siapa yang bertanggung jawab atas eksekusi"
                :disabled="isLocked"
            />
        </div>

        <!-- Immediate Actions -->
        <div class="response-field">
            <BaseTextarea
                v-model="draft.immediate_actions"
                label="Tindakan Segera"
                placeholder="Langkah-langkah yang harus dilakukan segera"
                :rows="3"
                :disabled="isLocked"
            />
        </div>

        <!-- Escalation -->
        <div class="response-field">
            <BaseTextarea
                v-model="draft.escalation"
                label="Eskalasi"
                placeholder="Kondisi atau ambang batas untuk eskalasi"
                :rows="2"
                :disabled="isLocked"
            />
        </div>

        <!-- Unknowns -->
        <div class="response-field">
            <BaseTextarea
                v-model="draft.unknowns"
                label="Yang Belum Diketahui"
                placeholder="Informasi yang masih perlu diklarifikasi"
                :rows="2"
                :disabled="isLocked"
            />
        </div>

        <!-- Notes (team response, NOT facilitator notes) -->
        <div class="response-field">
            <BaseTextarea
                v-model="draft.notes"
                label="Catatan Tim"
                placeholder="Catatan tambahan dari tim"
                :rows="2"
                :disabled="isLocked"
            />
        </div>

        <!-- Save button (only when active) -->
        <div v-if="isActive" class="response-actions">
            <BaseButton
                variant="primary"
                :loading="saving"
                :disabled="!canSave"
                @click="handleSave"
            >
                {{ isCreate ? 'Simpan Response' : 'Simpan Perubahan' }}
            </BaseButton>
            <span v-if="serverSnapshot" class="response-revision">
                Revisi #{{ serverSnapshot.revision }}
            </span>
        </div>

        <!-- Locked indicator -->
        <div v-if="isLocked" class="response-locked">
            <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <span>Response terkunci dan tidak dapat diubah.</span>
        </div>
    </div>
</template>

<style scoped>
.response-editor {
    display: flex;
    flex-direction: column;
    gap: var(--sp-4);
}

.response-field {
    display: flex;
    flex-direction: column;
    gap: var(--sp-1);
}

.response-hint {
    margin: 0;
    font-size: 0.75rem;
    color: var(--muted);
}

.response-actions {
    display: flex;
    align-items: center;
    gap: var(--sp-4);
    padding-top: var(--sp-4);
    border-top: 1px solid var(--line);
}

.response-revision {
    font-size: 0.75rem;
    color: var(--muted);
}

.response-locked {
    display: flex;
    align-items: center;
    gap: var(--sp-2);
    padding: var(--sp-3) var(--sp-4);
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    font-size: 0.875rem;
    color: var(--muted);
}
</style>
