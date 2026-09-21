<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import EmptyState from '@/Components/EmptyState.vue';
import RichContent from '@/Components/RichContent.vue';
import { useToast } from '@/Composables/useToast';
import InjectTimeline from './Partials/InjectTimeline.vue';

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
});

const toast = useToast();

// --- State ---
const session = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref(null);
const selectedInjectId = ref(null);

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

// --- Data Fetching ---
const fetchSession = async (isRefresh = false) => {
    if (isRefresh) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }
    error.value = null;

    try {
        const { data } = await axios.get(route('tenant.ttx.sessions.show', props.sessionId));
        session.value = data;

        // Auto-select: prefer current active inject, else first inject
        if (selectedInjectId.value === null) {
            const active = data.injects?.find((i) => i.status === 'active');
            selectedInjectId.value = active?.id ?? data.injects?.[0]?.id ?? null;
        }
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Gagal memuat data sesi.';
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const handleRefresh = () => fetchSession(true);

const handleSelect = (injectId) => {
    selectedInjectId.value = injectId;
};

// --- Response field labels ---
const responseFields = [
    { key: 'decision', label: 'Keputusan' },
    { key: 'rationale', label: 'Rationale' },
    { key: 'owner', label: 'Penanggung Jawab' },
    { key: 'immediate_actions', label: 'Tindakan Segera' },
    { key: 'escalation', label: 'Eskalasi' },
    { key: 'unknowns', label: 'Yang Belum Diketahui' },
    { key: 'notes', label: 'Catatan' },
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

                                    <EmptyState
                                        v-if="!selectedInject.response"
                                        title="Belum ada response"
                                        message="Tim belum mengirimkan response untuk injeksi ini."
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
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </main>
            </div>
        </template>
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
.console-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--sp-6);
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
}

.inject-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    padding: var(--sp-5) var(--sp-6);
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
}

.inject-card-body {
    padding: 0 var(--sp-6) var(--sp-5);
}

.inject-card-divider {
    height: 1px;
    background: var(--line);
    margin: 0 var(--sp-6);
}

/* Response section */
.inject-response {
    padding: var(--sp-5) var(--sp-6);
}

.inject-response-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 var(--sp-4);
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
}

.response-meta {
    display: flex;
    gap: var(--sp-4);
    flex-wrap: wrap;
    padding-top: var(--sp-3);
    border-top: 1px solid var(--line);
    font-size: 0.75rem;
    color: var(--muted);
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
}

@media (prefers-reduced-motion: reduce) {
    .skeleton {
        animation: none;
    }
}
</style>
