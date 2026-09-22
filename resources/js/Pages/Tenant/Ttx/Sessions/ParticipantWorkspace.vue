<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InjectTimeline from './Partials/InjectTimeline.vue';
import ResponseEditor from './Partials/ResponseEditor.vue';

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
});

const session = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const saving = ref(false);
const error = ref(null);
const selectedInjectId = ref(null);
const editorRef = ref(null);
const editorKey = ref(0);

const sortedInjects = computed(() => {
    return [...(session.value?.injects ?? [])].sort((a, b) => a.order - b.order);
});

const activeInject = computed(() => sortedInjects.value.find((inject) => inject.status === 'active') ?? null);
const selectedInject = computed(() => {
    return sortedInjects.value.find((inject) => inject.id === selectedInjectId.value) ?? null;
});

const isReady = computed(() => session.value?.status === 'ready');
const isInProgress = computed(() => session.value?.status === 'in_progress');
const isDebrief = computed(() => session.value?.status === 'debrief');
const isCompleted = computed(() => session.value?.status === 'completed');

const statusLabel = computed(() => ({
    draft: 'Draft',
    ready: 'Siap',
    in_progress: 'Berlangsung',
    debrief: 'Debrief',
    completed: 'Selesai',
}[session.value?.status] ?? session.value?.status ?? '-'));

const statusVariant = computed(() => ({
    draft: 'neutral',
    ready: 'info',
    in_progress: 'warning',
    debrief: 'brand',
    completed: 'success',
}[session.value?.status] ?? 'neutral'));

const roleLabel = computed(() => ({
    security: 'Keamanan',
    it_operations: 'Operasional TI',
    people_hr: 'SDM',
    communications: 'Komunikasi',
    management: 'Manajemen',
}[session.value?.actor_role] ?? session.value?.actor_role ?? '-'));

const knownFacts = computed(() => {
    const facts = selectedInject.value?.snapshot?.known_facts;
    if (Array.isArray(facts)) return facts.filter(Boolean);
    if (typeof facts === 'string' && facts.trim()) return [facts.trim()];
    return [];
});

const selectDefaultInject = () => {
    const stillAvailable = sortedInjects.value.some((inject) => inject.id === selectedInjectId.value);
    if (stillAvailable) return;

    selectedInjectId.value = activeInject.value?.id
        ?? sortedInjects.value.at(-1)?.id
        ?? null;
};

const fetchSession = async ({ refresh = false, resetEditor = false } = {}) => {
    if (refresh) refreshing.value = true;
    else loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get(route('tenant.ttx.sessions.show', props.sessionId));
        session.value = data;
        selectDefaultInject();
        if (resetEditor) editorKey.value += 1;
        await nextTick();
        editorRef.value?.clearErrors();
    } catch (err) {
        error.value = err.response?.status === 403
            ? 'Anda tidak memiliki akses ke workspace peserta sesi ini.'
            : (err.response?.data?.message ?? 'Data sesi tidak dapat dimuat.');
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const handleRefresh = () => {
    if (refreshing.value || saving.value) return;
    if (editorRef.value?.isDirty) {
        editorRef.value?.setGeneralError('Simpan respons atau muat versi terbaru sebelum menyegarkan halaman.');
        return;
    }
    fetchSession({ refresh: true, resetEditor: true });
};

const handleSelect = (injectId) => {
    if (injectId === selectedInjectId.value) return;
    if (editorRef.value?.isDirty) {
        editorRef.value?.setGeneralError('Simpan respons atau muat versi terbaru sebelum berpindah injeksi.');
        return;
    }
    selectedInjectId.value = injectId;
    editorKey.value += 1;
};

const responsePayload = (draft) => ({
    decision: draft.decision,
    rationale: draft.rationale || null,
    owner: draft.owner || null,
    immediate_actions: draft.immediate_actions || null,
    escalation: draft.escalation || null,
    unknowns: draft.unknowns || null,
    notes: draft.notes || null,
});

const handleSave = async ({ isCreate, draft, expectedRevision }) => {
    if (saving.value || !selectedInject.value || selectedInject.value.status !== 'active') return;
    saving.value = true;
    editorRef.value?.clearErrors();

    try {
        if (isCreate) {
            await axios.post(route('tenant.ttx.sessions.responses.store', props.sessionId), {
                session_inject_id: selectedInject.value.id,
                ...responsePayload(draft),
            });
        } else {
            await axios.put(
                route('tenant.ttx.sessions.responses.update', [props.sessionId, selectedInject.value.response.id]),
                { expected_revision: expectedRevision, ...responsePayload(draft) },
            );
        }

        await fetchSession({ refresh: true, resetEditor: true });
    } catch (err) {
        const status = err.response?.status;
        const data = err.response?.data;
        if (status === 409) {
            const responseWasCreated = data?.message?.includes('sudah ada');
            editorRef.value?.setConflictError(
                responseWasCreated
                    ? 'Respons resmi telah dibuat oleh anggota tim lain. Draf lokal Anda tetap tersimpan.'
                    : 'Respons resmi telah berubah sejak Anda membukanya. Draf lokal Anda tetap tersimpan.',
            );
        } else if (status === 422) {
            editorRef.value?.setValidationError(data?.errors ?? {});
        } else if (status === 403) {
            editorRef.value?.setAccessDeniedError('Anda tidak memiliki izin untuk mengubah respons ini.');
        } else {
            editorRef.value?.setGeneralError(data?.message ?? 'Respons tidak dapat disimpan.');
        }
    } finally {
        saving.value = false;
    }
};

const loadLatestResponse = () => fetchSession({ refresh: true, resetEditor: true });

onMounted(() => fetchSession());
</script>

<template>
    <Head title="Workspace Peserta" />

    <AppLayout title="Workspace Peserta">
        <div v-if="loading" class="space-y-6" aria-label="Memuat workspace peserta">
            <div class="skeleton h-32 rounded-2xl"></div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="skeleton h-80 rounded-2xl"></div>
                <div class="skeleton h-96 rounded-2xl lg:col-span-2"></div>
            </div>
        </div>

        <BaseAlert v-else-if="error" variant="danger" title="Gagal Memuat">
            <p>{{ error }}</p>
            <BaseButton variant="secondary" size="sm" class="mt-3" @click="fetchSession()">
                Coba Lagi
            </BaseButton>
        </BaseAlert>

        <div v-else-if="session" class="participant-workspace fade-in">
            <header class="workspace-hero">
                <div class="min-w-0">
                    <p class="workspace-eyebrow">Tabletop Exercise</p>
                    <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">{{ session.title }}</h1>
                    <div class="workspace-meta">
                        <BaseBadge :variant="statusVariant">{{ statusLabel }}</BaseBadge>
                        <BaseBadge variant="brand">{{ roleLabel }}</BaseBadge>
                    </div>
                </div>
                <BaseButton
                    variant="secondary"
                    size="sm"
                    :loading="refreshing"
                    :disabled="refreshing || saving"
                    @click="handleRefresh"
                >
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Segarkan
                </BaseButton>
            </header>

            <BaseAlert v-if="isReady" variant="info" title="Menunggu fasilitator memulai sesi">
                Workspace akan menampilkan situasi pertama setelah fasilitator memulai exercise.
            </BaseAlert>

            <BaseAlert v-else-if="isDebrief" variant="info" title="Sesi memasuki tahap debrief">
                Seluruh injeksi yang telah dirilis dan respons resmi tersedia dalam mode baca-saja.
            </BaseAlert>

            <BaseAlert v-else-if="isCompleted" variant="success" title="Exercise telah selesai">
                Timeline yang telah dirilis tetap tersedia dalam mode baca-saja.
            </BaseAlert>

            <div v-if="sortedInjects.length" class="workspace-grid">
                <aside class="workspace-timeline">
                    <InjectTimeline
                        :injects="sortedInjects"
                        :selected-id="selectedInjectId"
                        :current-inject-order="activeInject?.order ?? null"
                        @select="handleSelect"
                    />
                </aside>

                <main v-if="selectedInject" class="workspace-main">
                    <section class="content-card">
                        <div class="content-heading">
                            <div>
                                <p class="workspace-eyebrow">Injeksi {{ selectedInject.order }}</p>
                                <h2 class="font-display text-xl font-bold t-ink">
                                    {{ selectedInject.snapshot?.title ?? `Injeksi ${selectedInject.order}` }}
                                </h2>
                            </div>
                            <BaseBadge :variant="selectedInject.status === 'active' ? 'warning' : 'success'">
                                {{ selectedInject.status === 'active' ? 'Aktif' : 'Terkunci' }}
                            </BaseBadge>
                        </div>

                        <div class="content-section">
                            <h3>Situasi</h3>
                            <p>{{ selectedInject.snapshot?.situation ?? selectedInject.snapshot?.description ?? 'Belum ada rincian situasi.' }}</p>
                        </div>

                        <div v-if="knownFacts.length" class="content-section">
                            <h3>Fakta yang Diketahui</h3>
                            <ul class="fact-list">
                                <li v-for="fact in knownFacts" :key="fact">{{ fact }}</li>
                            </ul>
                        </div>

                        <div v-if="selectedInject.snapshot?.discussion_prompt" class="content-section prompt-panel">
                            <h3>Prompt Diskusi</h3>
                            <p>{{ selectedInject.snapshot.discussion_prompt }}</p>
                        </div>
                    </section>

                    <section class="content-card">
                        <div class="content-heading">
                            <div>
                                <p class="workspace-eyebrow">Respons Resmi</p>
                                <h2 class="font-display text-xl font-bold t-ink">Respons Tim</h2>
                            </div>
                            <span v-if="selectedInject.status === 'active'" class="edit-indicator">Dapat diedit</span>
                        </div>

                        <ResponseEditor
                            :key="`${selectedInject.id}-${editorKey}`"
                            ref="editorRef"
                            :inject-status="selectedInject.status"
                            :inject-id="selectedInject.id"
                            :response="selectedInject.response"
                            :saving="saving"
                            @save="handleSave"
                            @discard="loadLatestResponse"
                        />
                    </section>
                </main>
            </div>

            <EmptyState
                v-else-if="!isReady"
                title="Belum ada injeksi yang dirilis"
                message="Timeline akan muncul setelah fasilitator merilis injeksi untuk tim."
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.participant-workspace {
    display: flex;
    flex-direction: column;
    gap: var(--sp-6);
    min-width: 0;
}

.workspace-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    padding: var(--sp-6);
    border: 1px solid var(--line);
    border-radius: var(--r-2xl);
    background: var(--surface);
    box-shadow: var(--shadow-sm);
}

.workspace-eyebrow {
    margin: 0 0 var(--sp-1);
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.workspace-meta,
.content-heading {
    display: flex;
    align-items: center;
    gap: var(--sp-3);
}

.workspace-meta {
    margin-top: var(--sp-3);
    flex-wrap: wrap;
}

.workspace-grid {
    display: grid;
    grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
    gap: var(--sp-6);
    align-items: start;
    min-width: 0;
}

.workspace-timeline {
    position: sticky;
    top: var(--sp-6);
    min-width: 0;
}

.workspace-main {
    display: flex;
    flex-direction: column;
    gap: var(--sp-6);
    min-width: 0;
}

.content-card {
    padding: var(--sp-6);
    border: 1px solid var(--line);
    border-radius: var(--r-2xl);
    background: var(--surface);
    box-shadow: var(--shadow-sm);
    min-width: 0;
}

.content-heading {
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: var(--sp-4);
    border-bottom: 1px solid var(--line);
}

.content-section {
    margin-top: var(--sp-5);
    overflow-wrap: anywhere;
}

.content-section h3 {
    margin: 0 0 var(--sp-2);
    color: var(--t-ink);
    font-size: 0.875rem;
    font-weight: 700;
}

.content-section p,
.fact-list {
    margin: 0;
    color: var(--muted);
    line-height: 1.7;
    white-space: pre-wrap;
}

.fact-list {
    padding-left: 1.25rem;
}

.fact-list li + li {
    margin-top: var(--sp-2);
}

.prompt-panel {
    padding: var(--sp-4);
    border: 1px solid var(--line);
    border-radius: var(--r-xl);
    background: var(--surface-2);
}

.edit-indicator {
    color: var(--success);
    font-size: 0.75rem;
    font-weight: 700;
}

@media (max-width: 768px) {
    .workspace-hero {
        flex-direction: column;
    }

    .workspace-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .workspace-timeline {
        position: static;
    }

    .content-card,
    .workspace-hero {
        padding: var(--sp-4);
    }
}
</style>
