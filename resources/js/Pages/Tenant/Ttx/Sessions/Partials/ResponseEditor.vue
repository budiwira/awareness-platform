<script setup>
import { computed, ref, watch } from "vue";
import BaseTextarea from "@/Components/BaseTextarea.vue";
import BaseButton from "@/Components/BaseButton.vue";
import BaseAlert from "@/Components/BaseAlert.vue";

const props = defineProps({
    injectStatus: { type: String, required: true },
    response: { type: Object, default: null },
    injectId: { type: Number, required: true },
    contractVersion: { type: Number, default: 1 },
    teamName: { type: String, default: "Tim Anda" },
    saving: { type: Boolean, default: false },
    saveMessage: { type: String, default: null },
    lastSavedAt: { type: String, default: null },
});
const emit = defineEmits(["save", "discard"]);
const draft = ref(emptyDraft());
const serverSnapshot = ref(null);
const currentRevision = ref(null);
const editing = ref(true);
const conflictError = ref(null);
const validationErrors = ref(null);
const generalError = ref(null);
const accessDeniedError = ref(null);

function emptyDraft() {
    return {
        decision: "",
        rationale: "",
        owner: "",
        immediate_actions: "",
        coordination_handoff: "",
        escalation: "",
        unknowns: "",
        notes: "",
    };
}
const isV2 = computed(() => props.contractVersion >= 2);
const isCreate = computed(() => !serverSnapshot.value);
const isLocked = computed(() => props.injectStatus === "locked");
const isActive = computed(() => props.injectStatus === "active");
const editableFields = computed(() =>
    isV2.value
        ? [
              "decision",
              "rationale",
              "immediate_actions",
              "coordination_handoff",
              "escalation",
              "unknowns",
              "notes",
          ]
        : [
              "decision",
              "rationale",
              "owner",
              "immediate_actions",
              "escalation",
              "unknowns",
              "notes",
          ],
);

watch(
    () => props.response,
    (response) => {
        serverSnapshot.value = response ? { ...response } : null;
        currentRevision.value = response?.revision ?? null;
        draft.value = response
            ? Object.fromEntries(
                  Object.keys(emptyDraft()).map((key) => [
                      key,
                      response[key] ?? "",
                  ]),
              )
            : emptyDraft();
        editing.value = !response;
        conflictError.value = null;
        validationErrors.value = null;
        generalError.value = null;
        accessDeniedError.value = null;
    },
    { immediate: true },
);

const isDirty = computed(() => {
    if (!editing.value) return false;
    if (!serverSnapshot.value)
        return editableFields.value.some(
            (field) => draft.value[field].trim() !== "",
        );
    return editableFields.value.some(
        (field) =>
            draft.value[field].trim() !==
            (serverSnapshot.value[field] ?? "").trim(),
    );
});
const missingCoreFields = computed(() => {
    const required = isV2.value
        ? ["decision", "rationale", "immediate_actions", "coordination_handoff"]
        : ["decision"];
    const labels = {
        decision: "Keputusan Tim",
        rationale: "Alasan Keputusan",
        immediate_actions: "Tindakan Segera",
        coordination_handoff: "Koordinasi / Handoff",
    };
    return required
        .filter((field) => !draft.value[field].trim())
        .map((field) => labels[field]);
});
const canSave = computed(
    () =>
        !props.saving &&
        isActive.value &&
        !isLocked.value &&
        missingCoreFields.value.length === 0 &&
        (isCreate.value || isDirty.value),
);
const fieldErrors = computed(() =>
    Object.fromEntries(
        Object.entries(validationErrors.value ?? {}).map(([key, value]) => [
            key,
            Array.isArray(value) ? value[0] : value,
        ]),
    ),
);
const timestamp = computed(
    () =>
        props.lastSavedAt ??
        serverSnapshot.value?.last_edited_at ??
        serverSnapshot.value?.submitted_at ??
        null,
);
const formattedTimestamp = computed(() =>
    timestamp.value
        ? new Intl.DateTimeFormat("id-ID", {
              day: "numeric",
              month: "short",
              hour: "2-digit",
              minute: "2-digit",
          }).format(new Date(timestamp.value))
        : null,
);

function handleSave() {
    if (canSave.value)
        emit("save", {
            isCreate: isCreate.value,
            draft: { ...draft.value },
            expectedRevision: currentRevision.value,
        });
}
function setConflictError(message) {
    conflictError.value = message;
    validationErrors.value = null;
    generalError.value = null;
}
function setValidationError(errors) {
    validationErrors.value = errors;
    conflictError.value = null;
    generalError.value = null;
}
function setGeneralError(message) {
    generalError.value = message;
    conflictError.value = null;
    validationErrors.value = null;
}
function setAccessDeniedError(message) {
    accessDeniedError.value = message;
    conflictError.value = null;
    validationErrors.value = null;
    generalError.value = null;
}
function clearErrors() {
    conflictError.value = null;
    validationErrors.value = null;
    generalError.value = null;
    accessDeniedError.value = null;
}
function resetDraft() {
    serverSnapshot.value = null;
    currentRevision.value = null;
    draft.value = emptyDraft();
    editing.value = true;
    clearErrors();
}
defineExpose({
    isDirty,
    serverSnapshot,
    currentRevision,
    setConflictError,
    setValidationError,
    setGeneralError,
    setAccessDeniedError,
    clearErrors,
    resetDraft,
});
</script>

<template>
    <div class="response-editor" role="form" aria-label="Respons Tim">
        <BaseAlert
            v-if="conflictError"
            variant="warning"
            title="Keputusan tim telah berubah"
            aria-live="assertive"
        >
            <p>{{ conflictError }}</p>
            <p class="mt-1">Draf yang sedang Anda edit tidak dibuang.</p>
            <BaseButton
                variant="secondary"
                size="sm"
                class="mt-3"
                @click="$emit('discard', { reason: 'conflict-load-latest' })"
                >Muat Versi Tim Terbaru</BaseButton
            >
        </BaseAlert>
        <BaseAlert
            v-if="generalError"
            variant="danger"
            title="Keputusan belum dikirim"
            >{{ generalError }}</BaseAlert
        >
        <BaseAlert
            v-if="accessDeniedError"
            variant="danger"
            title="Akses ditolak"
            >{{ accessDeniedError }}</BaseAlert
        >
        <BaseAlert
            v-if="
                fieldErrors.session ||
                fieldErrors.inject ||
                fieldErrors.response
            "
            variant="danger"
            >{{
                fieldErrors.session ||
                fieldErrors.inject ||
                fieldErrors.response
            }}</BaseAlert
        >

        <section
            v-if="serverSnapshot && !editing"
            class="submitted-state"
            aria-live="polite"
        >
            <p class="state-label">
                {{ isLocked ? "KEPUTUSAN TERKUNCI" : "KEPUTUSAN DIKIRIM" }}
            </p>
            <h3>{{ teamName }} telah mengirim keputusan untuk situasi ini.</h3>
            <p v-if="!isLocked" class="state-copy">
                Keputusan masih dapat diperbarui sampai fasilitator melanjutkan
                exercise.
            </p>
            <p
                v-if="serverSnapshot.last_edited_by_name || formattedTimestamp"
                class="attribution"
            >
                Terakhir diperbarui<span
                    v-if="serverSnapshot.last_edited_by_name"
                >
                    oleh {{ serverSnapshot.last_edited_by_name }}</span
                ><span v-if="formattedTimestamp">
                    · {{ formattedTimestamp }}</span
                >
            </p>
            <dl class="decision-review">
                <div>
                    <dt>Keputusan</dt>
                    <dd>{{ serverSnapshot.decision }}</dd>
                </div>
                <div v-if="serverSnapshot.rationale">
                    <dt>Alasan</dt>
                    <dd>{{ serverSnapshot.rationale }}</dd>
                </div>
                <div v-if="serverSnapshot.immediate_actions">
                    <dt>Tindakan Segera</dt>
                    <dd>{{ serverSnapshot.immediate_actions }}</dd>
                </div>
                <div v-if="isV2 && serverSnapshot.coordination_handoff">
                    <dt>Koordinasi / Handoff</dt>
                    <dd>{{ serverSnapshot.coordination_handoff }}</dd>
                </div>
                <div v-if="!isV2 && serverSnapshot.owner">
                    <dt>Penanggung Jawab</dt>
                    <dd>{{ serverSnapshot.owner }}</dd>
                </div>
                <div v-if="serverSnapshot.escalation">
                    <dt>Eskalasi</dt>
                    <dd>{{ serverSnapshot.escalation }}</dd>
                </div>
                <div v-if="serverSnapshot.unknowns">
                    <dt>Yang Belum Diketahui</dt>
                    <dd>{{ serverSnapshot.unknowns }}</dd>
                </div>
                <div v-if="serverSnapshot.notes">
                    <dt>Catatan Tim</dt>
                    <dd>{{ serverSnapshot.notes }}</dd>
                </div>
            </dl>
            <BaseButton
                v-if="isActive"
                variant="secondary"
                @click="editing = true"
                >Edit Keputusan Tim</BaseButton
            >
            <p class="waiting-copy">
                {{
                    isLocked
                        ? "Keputusan ini bersifat baca-saja."
                        : "Menunggu fasilitator merilis perkembangan berikutnya."
                }}
            </p>
        </section>

        <section
            v-else-if="isLocked && !serverSnapshot"
            class="locked-note"
            role="status"
        >
            Tim tidak mengirim keputusan untuk situasi ini. Situation update
            telah terkunci.
        </section>

        <template v-else>
            <BaseTextarea
                v-model="draft.decision"
                label="Keputusan Tim"
                hint="Apa keputusan utama tim Anda?"
                :error="fieldErrors.decision"
                :rows="4"
                :disabled="isLocked"
                required
            />
            <BaseTextarea
                v-model="draft.rationale"
                :label="isV2 ? 'Alasan Keputusan' : 'Rationale'"
                :hint="
                    isV2
                        ? 'Mengapa tim mengambil keputusan tersebut?'
                        : 'Jelaskan alasan di balik keputusan ini.'
                "
                :error="fieldErrors.rationale"
                :rows="4"
                :disabled="isLocked"
                :required="isV2"
            />
            <BaseTextarea
                v-model="draft.immediate_actions"
                label="Tindakan Segera"
                :hint="
                    isV2
                        ? 'Apa yang akan dilakukan sekarang?'
                        : 'Langkah yang harus dilakukan segera.'
                "
                :error="fieldErrors.immediate_actions"
                :rows="4"
                :disabled="isLocked"
                :required="isV2"
            />
            <BaseTextarea
                v-if="isV2"
                v-model="draft.coordination_handoff"
                label="Koordinasi / Handoff"
                hint="Tim atau stakeholder mana yang perlu dilibatkan, dan apa yang dibutuhkan?"
                :error="fieldErrors.coordination_handoff"
                :rows="4"
                :disabled="isLocked"
                required
            />
            <BaseTextarea
                v-else
                v-model="draft.owner"
                label="Penanggung Jawab"
                hint="Siapa yang bertanggung jawab atas eksekusi?"
                :error="fieldErrors.owner"
                :rows="2"
                :disabled="isLocked"
            />
            <details class="secondary-fields">
                <summary>Additional Context</summary>
                <div class="secondary-content">
                    <BaseTextarea
                        v-model="draft.escalation"
                        label="Eskalasi"
                        :error="fieldErrors.escalation"
                        :rows="3"
                        :disabled="isLocked"
                    /><BaseTextarea
                        v-model="draft.unknowns"
                        label="Yang Belum Diketahui"
                        :error="fieldErrors.unknowns"
                        :rows="3"
                        :disabled="isLocked"
                    /><BaseTextarea
                        v-model="draft.notes"
                        label="Catatan Tim"
                        :error="fieldErrors.notes"
                        :rows="3"
                        :disabled="isLocked"
                    />
                </div>
            </details>
            <div v-if="isActive" class="response-actions">
                <div>
                    <p
                        v-if="missingCoreFields.length"
                        class="validation-summary"
                        role="status"
                    >
                        Lengkapi: {{ missingCoreFields.join(", ") }}.
                    </p>
                    <p v-else-if="!isCreate && !isDirty" class="response-hint">
                        Belum ada perubahan untuk dikirim.
                    </p>
                </div>
                <BaseButton
                    variant="primary"
                    :loading="saving"
                    :disabled="!canSave"
                    @click="handleSave"
                    >{{
                        saving
                            ? "Mengirim..."
                            : isCreate
                              ? "Kirim Keputusan Tim"
                              : "Perbarui Keputusan Tim"
                    }}</BaseButton
                >
            </div>
            <div v-if="isLocked" class="locked-note">
                Keputusan terkunci dan tidak dapat diubah.
            </div>
        </template>
    </div>
</template>

<style scoped>
.response-editor {
    display: flex;
    flex-direction: column;
    gap: var(--sp-5);
    min-width: 0;
}
.response-editor :deep(textarea) {
    min-height: 7rem;
    border-radius: var(--r-lg);
    line-height: 1.55;
    overflow-wrap: anywhere;
}
.response-editor :deep(textarea:focus) {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand) 22%, transparent);
}
.secondary-fields {
    border-top: 1px solid var(--line);
    padding-top: var(--sp-4);
}
.secondary-fields summary {
    width: max-content;
    max-width: 100%;
    cursor: pointer;
    color: var(--ink);
    font-size: 0.875rem;
    font-weight: 600;
}
.secondary-fields summary:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 3px;
}
.secondary-content {
    display: grid;
    gap: var(--sp-4);
    margin-top: var(--sp-4);
}
.response-actions {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--sp-4);
    padding-top: var(--sp-4);
    border-top: 1px solid var(--line);
}
.validation-summary {
    margin: 0;
    color: var(--danger);
    font-size: 0.8125rem;
}
.response-hint,
.waiting-copy,
.state-copy,
.attribution {
    margin: 0;
    color: var(--muted);
    font-size: 0.875rem;
    line-height: 1.6;
}
.submitted-state {
    padding: var(--sp-5);
    border-left: 3px solid var(--ok);
    background: var(--surface-2);
}
.state-label {
    margin: 0;
    color: var(--ok);
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.08em;
}
.submitted-state h3 {
    margin: var(--sp-2) 0;
    color: var(--ink);
    font-size: 1rem;
    font-weight: 700;
}
.attribution {
    margin-top: var(--sp-2);
}
.decision-review {
    display: grid;
    gap: var(--sp-4);
    margin: var(--sp-5) 0;
    padding-top: var(--sp-4);
    border-top: 1px solid var(--line);
}
.decision-review dt {
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.decision-review dd {
    margin-top: 0.3rem;
    color: var(--ink);
    line-height: 1.65;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.waiting-copy {
    margin-top: var(--sp-3);
}
.locked-note {
    padding: var(--sp-3);
    border-left: 3px solid var(--line);
    background: var(--surface-2);
    color: var(--muted);
    font-size: 0.875rem;
}
@media (max-width: 640px) {
    .response-actions {
        align-items: stretch;
        flex-direction: column;
    }
    .response-actions :deep(button) {
        width: 100%;
    }
    .submitted-state {
        padding: var(--sp-4);
    }
}
</style>
