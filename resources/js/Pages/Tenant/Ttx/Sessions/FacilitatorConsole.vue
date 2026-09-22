<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import EmptyState from '@/Components/EmptyState.vue';
import RichContent from '@/Components/RichContent.vue';
import Modal from '@/Components/Modal.vue';
import { useToast } from '@/Composables/useToast';
import InjectTimeline from './Partials/InjectTimeline.vue';
import ResponseEditor from './Partials/ResponseEditor.vue';

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
});

const toast = useToast();

// --- State ---
const session = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const saving = ref(false);
const starting = ref(false);
const advancing = ref(false);
const error = ref(null);
const accessDeniedError = ref(null);
const selectedInjectId = ref(null);

// Response editor ref
const editorRef = ref(null);
// Key that increments on every session refresh, forcing ResponseEditor to
// re-mount so its internal draft state resets to the authoritative server value.
const refreshKey = ref(0);
// v-if toggle: briefly set to false to force ResponseEditor DOM destruction,
// then back to true in nextTick to force recreation. More reliable than :key
// alone because Vue's scheduler may batch key updates without triggering
// component destruction in certain timing scenarios.
const editorMounted = ref(true);

// --- Unsaved changes modal ---
const showUnsavedModal = ref(false);
const pendingAction = ref(null); // { type: 'select', injectId } | { type: 'refresh' }

// --- Computed ---
const sortedInjects = computed(() => {
    if (!session.value?.injects) return [];
    return [...session.value.injects].sort((a, b) => a.order - b.order);
});

const selectedInject = computed(() => {
    if (!selectedInjectId.value || !session.value?.injects) return null;
    return session.value.injects.find((i) => i.id === selectedInjectId.value) ?? null;
});

const currentInjectOrder = computed(() => session.value?.progress?.current ?? null);

const statusLabel = computed(() => {
    const map = {
        draft: 'Draft',
        ready: 'Siap',
        in_progress: 'Berlangsung',
        debrief: 'Debrief',
        completed: 'Selesai',
    };
    return map[session.value?.status] ?? session.value?.status ?? '-';
});

const statusVariant = computed(() => {
    const map = {
        draft: 'neutral',
        ready: 'info',
        in_progress: 'warning',
        debrief: 'brand',
        completed: 'success',
    };
    return map[session.value?.status] ?? 'neutral';
});

// --- Lifecycle computed ---
const isReady = computed(() => session.value?.status === 'ready');
const isDraft = computed(() => session.value?.status === 'draft');
const isInProgress = computed(() => session.value?.status === 'in_progress');
const isDebrief = computed(() => session.value?.status === 'debrief');
const isCompleted = computed(() => session.value?.status === 'completed');

const activeInject = computed(() => {
    if (!session.value?.injects) return null;
    return session.value.injects.find((i) => i.status === 'active') ?? null;
});

const isFinalInject = computed(() => {
    if (!activeInject.value || !session.value?.injects) return false;
    const pendingCount = session.value.injects.filter((i) => i.status === 'pending').length;
    return pendingCount === 0;
});

// --- Start exercise ---
const handleStart = async () => {
    if (starting.value || refreshing.value || !isReady.value) return;
    starting.value = true;
    error.value = null;

    try {
        await axios.post(route('tenant.ttx.sessions.start', props.sessionId));
        toast.success('Exercise berhasil dimulai.');
        await fetchSession(true);
        // Select the new ACTIVE inject
        nextTick(() => {
            if (activeInject.value) {
                selectedInjectId.value = activeInject.value.id;
            }
        });
    } catch (err) {
        const status = err.response?.status;
        const msg = err.response?.data?.message ?? 'Gagal memulai exercise.';
        if (status === 403) {
            accessDeniedError.value = 'Anda tidak memiliki izin untuk melakukan tindakan ini.';
        } else if (status === 409 || status === 422) {
            // State conflict or validation: refresh to get authoritative state
            toast.error(msg);
            await fetchSession(true);
        } else {
            toast.error(msg);
        }
    } finally {
        starting.value = false;
    }
};

// --- Advance inject ---
const canAdvance = computed(() => {
    if (!isInProgress.value) return false;
    if (!activeInject.value) return false;
    if (advancing.value) return false;
    if (saving.value) return false;
    if (refreshing.value) return false;
    if (editorRef.value?.isDirty) return false;
    // Must have a response with non-empty decision
    const response = activeInject.value.response;
    if (!response) return false;
    if (!response.decision || !response.decision.trim()) return false;
    return true;
});

const advanceLabel = computed(() => {
    return isFinalInject.value ? 'Selesaikan Inject & Masuk Debrief' : 'Rilis Inject Berikutnya';
});

const handleAdvance = async () => {
    if (!canAdvance.value) return;
    advancing.value = true;

    try {
        await axios.post(route('tenant.ttx.sessions.advance', props.sessionId));
        toast.success('Inject berhasil dilanjutkan.');
        await fetchSession(true);
        // Select new active inject, or most recent locked if debrief
        nextTick(() => {
            if (activeInject.value) {
                selectedInjectId.value = activeInject.value.id;
            } else if (isDebrief.value) {
                // Select the most recently locked inject
                const locked = sortedInjects.value.filter((i) => i.status === 'locked');
                if (locked.length) {
                    selectedInjectId.value = locked[locked.length - 1].id;
                }
            }
        });
    } catch (err) {
        const status = err.response?.status;
        const msg = err.response?.data?.message ?? 'Gagal melanjutkan inject.';
        if (status === 403) {
            accessDeniedError.value = 'Anda tidak memiliki izin untuk melakukan tindakan ini.';
        } else if (status === 409 || status === 422) {
            toast.error(msg);
            await fetchSession(true);
        } else {
            toast.error(msg);
        }
    } finally {
        advancing.value = false;
    }
};

// --- Data Fetching ---
const fetchSession = async (isRefresh = false) => {
    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }
    error.value = null;
    accessDeniedError.value = null;

    try {
        const { data } = await axios.get(route('tenant.ttx.sessions.show', props.sessionId));
        session.value = data;

        // Bump key so ResponseEditor re-mounts with fresh server state.
        // The re-mount triggers the immediate watcher inside ResponseEditor,
        // which correctly syncs the local draft with the authoritative server
        // response. No explicit resetDraft() call needed here -- the watcher
        // handles it, and calling resetDraft() AFTER the watcher would clear
        // the correctly-populated draft back to empty (the exact defect that
        // was fixed in TTX2-D3-B1).
        if (isRefresh) {
            refreshKey.value++;
        }

        // Auto-select: prefer current active inject, else first inject
        if (selectedInjectId.value === null) {
            const active = data.injects?.find((i) => i.status === 'active');
            selectedInjectId.value = active?.id ?? data.injects?.[0]?.id ?? null;
        }

        // After fetch, clear any conflict/validation errors in the editor
        nextTick(() => editorRef.value?.clearErrors());
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Gagal memuat data sesi.';
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

// --- Refresh with dirty protection ---
const handleRefresh = () => {
    if (editorRef.value?.isDirty) {
        pendingAction.value = { type: 'refresh' };
        showUnsavedModal.value = true;
        return;
    }
    fetchSession(true);
};

// --- Conflict resolution: load latest version ---
const handleConflictLoadLatest = async () => {
    await fetchSession(true);
};

// --- Timeline selection with dirty protection ---
const handleSelect = (injectId) => {
    if (injectId === selectedInjectId.value) return;
    if (editorRef.value?.isDirty) {
        pendingAction.value = { type: 'select', injectId };
        showUnsavedModal.value = true;
        return;
    }
    selectedInjectId.value = injectId;
};

// --- Unsaved modal actions ---
const handleUnsavedStay = () => {
    showUnsavedModal.value = false;
    pendingAction.value = null;
};

const handleUnsavedDiscard = () => {
    showUnsavedModal.value = false;
    const action = pendingAction.value;
    pendingAction.value = null;

    // Force ResponseEditor to fully destroy and recreate with fresh (empty)
    // state. The v-if toggle (false -> true) guarantees Vue removes the old
    // DOM subtree and creates a new one, unlike :key which may be batched
    // in the same render cycle. We use setTimeout (macrotask) instead of
    // nextTick (microtask) so Vue completes the removal render before we
    // set the flag back to true in a separate render cycle.
    editorMounted.value = false;
    setTimeout(() => {
        editorMounted.value = true;
        refreshKey.value++;
    }, 0);

    if (action?.type === 'select') {
        selectedInjectId.value = action.injectId;
    } else if (action?.type === 'refresh') {
        fetchSession(true);
    }
};

// --- Response save ---
const handleSave = async ({ isCreate, draft, expectedRevision }) => {
    if (saving.value) return;
    saving.value = true;

    try {
        if (isCreate) {
            await createResponse(draft);
        } else {
            await updateResponse(draft, expectedRevision);
        }
    } catch (err) {
        handleSaveError(err);
    } finally {
        saving.value = false;
    }
};

const createResponse = async (draft) => {
    const payload = {
        session_inject_id: selectedInjectId.value,
        decision: draft.decision,
        rationale: draft.rationale || null,
        owner: draft.owner || null,
        immediate_actions: draft.immediate_actions || null,
        escalation: draft.escalation || null,
        unknowns: draft.unknowns || null,
        notes: draft.notes || null,
    };

    await axios.post(route('tenant.ttx.sessions.responses.store', props.sessionId), payload);

    toast.success('Response berhasil disimpan.');
    await fetchSession(true);
};

const updateResponse = async (draft, expectedRevision) => {
    const responseId = selectedInject.value?.response?.id;
    if (!responseId) return;

    const payload = {
        expected_revision: expectedRevision,
        decision: draft.decision,
        rationale: draft.rationale || null,
        owner: draft.owner || null,
        immediate_actions: draft.immediate_actions || null,
        escalation: draft.escalation || null,
        unknowns: draft.unknowns || null,
        notes: draft.notes || null,
    };

    await axios.put(
        route('tenant.ttx.sessions.responses.update', [props.sessionId, responseId]),
        payload,
    );

    toast.success('Response berhasil diperbarui.');
    await fetchSession(true);
};

// --- Error handling ---
const handleSaveError = (err) => {
    const status = err.response?.status;
    const data = err.response?.data;

    if (status === 409) {
        const msg = data?.message ?? 'Versi response tidak sesuai.';
        // Distinguish between stale-update 409 and duplicate-create 409
        if (msg.includes('sudah ada') || msg.includes('sudah dibuat')) {
            // Duplicate create conflict
            editorRef.value?.setConflictError(
                'Response untuk inject ini sudah dibuat oleh pengguna lain.',
            );
        } else {
            // Stale revision conflict
            editorRef.value?.setConflictError(msg);
        }
    } else if (status === 422) {
        editorRef.value?.setValidationError(data?.errors ?? {});
        // If lifecycle errors suggest state may have changed, show a hint
        const errors = data?.errors ?? {};
        if (errors.session || errors.inject || errors.response) {
            toast.error('Status injeksi atau sesi mungkin telah berubah. Periksa pesan di atas.');
        }
    } else if (status === 403) {
        editorRef.value?.setAccessDeniedError(
            'Anda tidak memiliki izin untuk melakukan tindakan ini.',
        );
    } else {
        editorRef.value?.setGeneralError(
            data?.message ?? 'Terjadi kesalahan saat menyimpan response.',
        );
    }
};

// --- Response field labels ---
const responseFields = [
    { key: 'decision', label: 'Keputusan' },
    { key: 'rationale', label: 'Rationale' },
    { key: 'owner', label: 'Penanggung Jawab' },
    { key: 'immediate_actions', label: 'Tindakan Segera' },
    { key: 'escalation', label: 'Eskalasi' },
    { key: 'unknowns', label: 'Yang Belum Diketahui' },
    { key: 'notes', label: 'Catatan Tim' },
];

const formatDate = (iso) => {
    if (!iso) return '-';
    try {
        return new Date(iso).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return iso;
    }
};

// --- Lifecycle ---
onMounted(() => fetchSession());
</script>

<template>
    <Head title="Konsol Fasilitator" />

    <AppLayout title="Konsol Fasilitator">
        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-6">
            <div class="skeleton h-32 rounded-2xl"></div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="skeleton h-96 rounded-2xl lg:col-span-1"></div>
                <div class="skeleton h-96 rounded-2xl lg:col-span-2"></div>
            </div>
        </div>

        <!-- Error state -->
        <BaseAlert v-else-if="error" variant="danger" title="Gagal Memuat">
            <p>{{ error }}</p>
            <BaseButton variant="secondary" size="sm" class="mt-3" @click="fetchSession">
                Coba Lagi
            </BaseButton>
        </BaseAlert>

        <!-- Main content -->
        <template v-else-if="session">
            <!-- Header -->
            <div class="console-header">
                <div class="console-header-main">
                    <Link
                        :href="route('tenant.ttx.exercises.index')"
                        class="console-back"
                    >
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Kembali
                    </Link>
                    <h1 class="font-display text-xl font-bold t-ink">{{ session.title }}</h1>
                    <div class="console-header-meta">
                        <BaseBadge :variant="statusVariant">{{ statusLabel }}</BaseBadge>
                        <span class="t-muted text-sm">
                            Inject {{ session.progress?.current ?? '-' }} dari {{ session.progress?.total ?? '-' }}
                        </span>
                        <BaseBadge variant="brand">Fasilitator</BaseBadge>
                    </div>
                </div>
                <BaseButton
                    variant="secondary"
                    size="sm"
                    :loading="refreshing"
                    @click="handleRefresh"
                >
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </BaseButton>
            </div>

            <!-- Access denied state (mutation 403) -->
            <BaseAlert
                v-if="accessDeniedError"
                variant="danger"
                title="Akses ditolak"
                class="mb-6"
            >
                {{ accessDeniedError }}
            </BaseAlert>

            <!-- DRAFT state: informational banner -->
            <BaseAlert
                v-if="isDraft"
                variant="info"
                title="Session belum siap untuk dimulai."
                class="mb-6"
            >
                Persiapan sesi harus diselesaikan terlebih dahulu oleh Tenant Admin sebelum exercise dapat dimulai.
            </BaseAlert>

            <!-- READY state: Start action -->
            <BaseAlert
                v-if="isReady"
                variant="info"
                title="Siap untuk dimulai"
                class="mb-6"
            >
                <p>Semua persiapan telah selesai. Tekan tombol di bawah untuk memulai exercise.</p>
                <BaseButton
                    variant="primary"
                    class="mt-3"
                    :loading="starting"
                    :disabled="starting"
                    @click="handleStart"
                >
                    Mulai Exercise
                </BaseButton>
            </BaseAlert>

            <!-- DEBRIEF banner -->
            <BaseAlert
                v-if="isDebrief"
                variant="info"
                title="Exercise telah memasuki fase debrief."
                class="mb-6"
            >
                Evaluation dan action items akan tersedia pada fase berikutnya. Timeline dan response yang sudah terkunci dapat ditinjau.
            </BaseAlert>

            <!-- COMPLETED banner -->
            <BaseAlert
                v-if="isCompleted"
                variant="success"
                title="Exercise telah selesai."
                class="mb-6"
            >
                Sesi ini sudah selesai dan tidak dapat diubah lagi.
            </BaseAlert>

            <!-- Two-column layout -->
            <div class="console-grid">
                <!-- Timeline sidebar (desktop: sticky left) -->
                <aside class="console-sidebar">
                    <InjectTimeline
                        :injects="sortedInjects"
                        :selected-id="selectedInjectId"
                        :current-inject-order="currentInjectOrder"
                        @select="handleSelect"
                    />
                </aside>

                <!-- Main content area -->
                <main class="console-main">
                    <EmptyState
                        v-if="!selectedInject"
                        title="Pilih Injeksi"
                        message="Pilih injeksi dari timeline untuk melihat detail."
                    />

                    <template v-else>
                        <!-- Inject detail card -->
                        <div class="inject-card">
                            <div class="inject-card-head">
                                <div>
                                    <div class="inject-card-order">Injeksi #{{ selectedInject.order }}</div>
                                    <h2 class="inject-card-title">
                                        {{ selectedInject.snapshot?.title ?? `Injeksi #${selectedInject.order}` }}
                                    </h2>
                                </div>
                                <BaseBadge
                                    :variant="
                                        selectedInject.status === 'active' ? 'warning'
                                            : selectedInject.status === 'locked' ? 'success'
                                            : 'neutral'
                                    "
                                >
                                    {{ selectedInject.status === 'active' ? 'Aktif'
                                        : selectedInject.status === 'locked' ? 'Selesai'
                                        : 'Pending' }}
                                </BaseBadge>
                            </div>

                            <div v-if="selectedInject.snapshot?.description" class="inject-card-body">
                                <RichContent :html="selectedInject.snapshot.description" />
                            </div>

                            <!-- Response section: only for Active/Locked injects -->
                            <template v-if="selectedInject.status !== 'pending'">
                                <div class="inject-card-divider"></div>
                                <div class="inject-response">
                                    <h3 class="inject-response-title">Response Tim</h3>

                                    <!-- ACTIVE: editable response editor -->
                                    <ResponseEditor
                                        v-if="selectedInject.status === 'active' && editorMounted"
                                        :key="`resp-${refreshKey}-${selectedInject.id}`"
                                        ref="editorRef"
                                        inject-status="active"
                                        :response="selectedInject.response"
                                        :inject-id="selectedInject.id"
                                        :saving="saving"
                                        @save="handleSave"
                                        @discard="handleConflictLoadLatest"
                                    />

                                    <!-- Advance action (in_progress only) -->
                                    <div v-if="isInProgress && selectedInject.status === 'active'" class="advance-section">
                                        <div v-if="editorRef?.isDirty" class="advance-hint">
                                            Simpan perubahan response sebelum melanjutkan.
                                        </div>
                                        <BaseButton
                                            variant="primary"
                                            :loading="advancing"
                                            :disabled="!canAdvance"
                                            @click="handleAdvance"
                                        >
                                            {{ advanceLabel }}
                                        </BaseButton>
                                    </div>

                                    <!-- LOCKED: read-only display -->
                                    <template v-else-if="selectedInject.status === 'locked'">
                                        <EmptyState
                                            v-if="!selectedInject.response"
                                            title="Belum ada response"
                                            message="Response belum dikirim untuk injeksi ini."
                                        />

                                        <div v-else class="response-fields">
                                            <div
                                                v-for="field in responseFields"
                                                :key="field.key"
                                                class="response-field"
                                            >
                                                <div class="response-field-label">{{ field.label }}</div>
                                                <div class="response-field-value">
                                                    {{ selectedInject.response[field.key] || '-' }}
                                                </div>
                                            </div>

                                            <div class="response-meta">
                                                <span v-if="selectedInject.response.submitted_at">
                                                    Dikirim: {{ formatDate(selectedInject.response.submitted_at) }}
                                                </span>
                                                <span v-if="selectedInject.response.locked_at">
                                                    Direvisi: {{ formatDate(selectedInject.response.locked_at) }}
                                                </span>
                                                <span v-if="selectedInject.response.revision">
                                                    Revisi #{{ selectedInject.response.revision }}
                                                </span>
                                            </div>

                                            <!-- Locked indicator -->
                                            <div class="response-locked">
                                                <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                <span>Response terkunci dan tidak dapat diubah.</span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </main>
            </div>
        </template>

        <!-- Unsaved changes modal -->
        <Modal :show="showUnsavedModal" max-width="md" @close="handleUnsavedStay">
            <div class="unsaved-modal">
                <h2 class="unsaved-modal-title">Perubahan belum disimpan</h2>
                <p class="unsaved-modal-message">
                    Jika Anda melanjutkan, perubahan lokal pada response akan hilang.
                </p>
                <div class="unsaved-modal-actions">
                    <BaseButton variant="secondary" @click="handleUnsavedStay">
                        Tetap di sini
                    </BaseButton>
                    <BaseButton variant="danger" @click="handleUnsavedDiscard">
                        Buang perubahan
                    </BaseButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
/* Header */
.console-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    margin-bottom: var(--sp-6);
    flex-wrap: wrap;
}

.console-header-main {
    display: flex;
    flex-direction: column;
    gap: var(--sp-2);
    min-width: 0;
}

.console-back {
    display: inline-flex;
    align-items: center;
    gap: var(--sp-1);
    font-size: 0.875rem;
    color: var(--muted);
    text-decoration: none;
    transition: color var(--dur) var(--ease);
}

.console-back:hover {
    color: var(--ink);
}

.console-back:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
    border-radius: var(--r-sm);
}

.console-header-meta {
    display: flex;
    align-items: center;
    gap: var(--sp-3);
    flex-wrap: wrap;
}

/* Grid layout */
.console-main {
    min-width: 0;
}

.console-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--sp-6);
    min-width: 0;
}

@media (min-width: 1024px) {
    .console-grid {
        grid-template-columns: 320px 1fr;
    }

    .console-sidebar {
        position: sticky;
        top: var(--sp-6);
        max-height: calc(100vh - 6rem);
        overflow-y: auto;
    }
}

/* Inject card */
.inject-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--r-card);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    min-width: 0;
}

.inject-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    padding: var(--sp-5) var(--sp-6);
    min-width: 0;
}

.inject-card-order {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted);
    margin-bottom: var(--sp-1);
}

.inject-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
    overflow-wrap: anywhere;
    min-width: 0;
}

.inject-card-body {
    padding: 0 var(--sp-6) var(--sp-5);
    min-width: 0;
}

.inject-card-divider {
    height: 1px;
    background: var(--line);
    margin: 0 var(--sp-6);
}

/* Response section */
.inject-response {
    padding: var(--sp-5) var(--sp-6);
    min-width: 0;
}

.inject-response-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 var(--sp-4);
    overflow-wrap: anywhere;
}

.response-fields {
    display: flex;
    flex-direction: column;
    gap: var(--sp-4);
}

.response-field {
    display: flex;
    flex-direction: column;
    gap: var(--sp-1);
}

.response-field-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--muted);
}

.response-field-value {
    font-size: 0.875rem;
    color: var(--ink);
    line-height: 1.6;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    min-width: 0;
}

.response-meta {
    display: flex;
    gap: var(--sp-4);
    flex-wrap: wrap;
    padding-top: var(--sp-3);
    border-top: 1px solid var(--line);
    font-size: 0.75rem;
    color: var(--muted);
    min-width: 0;
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
    margin-top: var(--sp-4);
    overflow-wrap: anywhere;
    min-width: 0;
}

/* Advance section */
.advance-section {
    display: flex;
    flex-direction: column;
    gap: var(--sp-3);
    padding-top: var(--sp-4);
    border-top: 1px solid var(--line);
}

.advance-hint {
    font-size: 0.8125rem;
    color: var(--warn);
    line-height: 1.5;
    overflow-wrap: anywhere;
}

/* Unsaved modal */
.unsaved-modal {
    padding: var(--sp-6);
}

.unsaved-modal-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 var(--sp-2);
    overflow-wrap: anywhere;
}

.unsaved-modal-message {
    font-size: 0.875rem;
    color: var(--muted);
    margin: 0 0 var(--sp-6);
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.unsaved-modal-actions {
    display: flex;
    gap: var(--sp-3);
    justify-content: flex-end;
    flex-wrap: wrap;
}


.unsaved-modal :deep(.base-btn--danger) {
    background: #7f1239;
    border-color: #7f1239;
    color: #ffffff;
}

.unsaved-modal :deep(.base-btn--danger > span) {
    color: #ffffff;
}

.unsaved-modal :deep(.base-btn--danger:hover:not(:disabled)) {
    background: #701a32;
    border-color: #701a32;
    color: #ffffff;
}
/* Skeleton */
.skeleton {
    background: linear-gradient(90deg, var(--skeleton-base) 25%, var(--skeleton-shine) 50%, var(--skeleton-base) 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Responsive: tablet */
@media (max-width: 1023px) and (min-width: 640px) {
    .console-grid {
        grid-template-columns: 1fr;
    }

    .console-sidebar :deep(.timeline-list) {
        flex-direction: row;
        overflow-x: auto;
        gap: var(--sp-2);
        padding-bottom: var(--sp-2);
    }

    .console-sidebar :deep(.timeline-item) {
        min-width: 200px;
        flex-shrink: 0;
    }
}

/* Responsive: mobile */
@media (max-width: 639px) {
    .console-header {
        flex-direction: column;
    }

    .inject-card-head {
        padding: var(--sp-4);
        flex-direction: column;
    }

    .inject-card-body {
        padding: 0 var(--sp-4) var(--sp-4);
    }

    .inject-card-divider {
        margin: 0 var(--sp-4);
    }

    .inject-response {
        padding: var(--sp-4);
    }

    .advance-section .base-btn {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton {
        animation: none;
    }
}
</style>
