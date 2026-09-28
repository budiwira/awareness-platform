<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseBadge from "@/Components/BaseBadge.vue";
import BaseButton from "@/Components/BaseButton.vue";
import BaseAlert from "@/Components/BaseAlert.vue";
import EmptyState from "@/Components/EmptyState.vue";
import RichContent from "@/Components/RichContent.vue";
import Modal from "@/Components/Modal.vue";
import { useToast } from "@/Composables/useToast";
import InjectTimeline from "./Partials/InjectTimeline.vue";

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
});

const toast = useToast();

// --- State ---
const session = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const starting = ref(false);
const advancing = ref(false);
const error = ref(null);
const accessDeniedError = ref(null);
const selectedInjectId = ref(null);
const showAdvanceWarning = ref(false);

// --- Computed ---
const sortedInjects = computed(() => {
    if (!session.value?.injects) return [];
    return [...session.value.injects].sort((a, b) => a.order - b.order);
});

const selectedInject = computed(() => {
    if (!selectedInjectId.value || !session.value?.injects) return null;
    return (
        session.value.injects.find((i) => i.id === selectedInjectId.value) ??
        null
    );
});

const currentInjectOrder = computed(
    () => session.value?.progress?.current ?? null,
);

const statusLabel = computed(() => {
    const map = {
        draft: "Draft",
        ready: "Siap",
        in_progress: "Berlangsung",
        debrief: "Debrief",
        completed: "Selesai",
    };
    return map[session.value?.status] ?? session.value?.status ?? "-";
});

const statusVariant = computed(() => {
    const map = {
        draft: "neutral",
        ready: "info",
        in_progress: "warning",
        debrief: "brand",
        completed: "success",
    };
    return map[session.value?.status] ?? "neutral";
});

// --- Lifecycle computed ---
const isReady = computed(() => session.value?.status === "ready");
const isDraft = computed(() => session.value?.status === "draft");
const isInProgress = computed(() => session.value?.status === "in_progress");
const isDebrief = computed(() => session.value?.status === "debrief");
const isCompleted = computed(() => session.value?.status === "completed");

const activeInject = computed(() => {
    if (!session.value?.injects) return null;
    return session.value.injects.find((i) => i.status === "active") ?? null;
});

const isFinalInject = computed(() => {
    if (!activeInject.value || !session.value?.injects) return false;
    const pendingCount = session.value.injects.filter(
        (i) => i.status === "pending",
    ).length;
    return pendingCount === 0;
});

// --- Start exercise ---
const handleStart = async () => {
    if (starting.value || refreshing.value || !isReady.value) return;
    starting.value = true;
    error.value = null;

    try {
        await axios.post(route("tenant.ttx.sessions.start", props.sessionId));
        toast.success("Exercise berhasil dimulai.");
        await fetchSession(true);
        // Select the new ACTIVE inject
        nextTick(() => {
            if (activeInject.value) {
                selectedInjectId.value = activeInject.value.id;
            }
        });
    } catch (err) {
        const status = err.response?.status;
        const msg = err.response?.data?.message ?? "Gagal memulai exercise.";
        if (status === 403) {
            accessDeniedError.value =
                "Anda tidak memiliki izin untuk melakukan tindakan ini.";
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
    if (refreshing.value) return false;
    return true;
});

const responseSummary = computed(
    () => activeInject.value?.response_summary ?? { responded: 0, total: 0 },
);
const missingTeamCount = computed(() =>
    Math.max(0, responseSummary.value.total - responseSummary.value.responded),
);
const missingTeams = computed(() =>
    (activeInject.value?.team_responses ?? [])
        .filter((entry) => !entry.response)
        .map((entry) => entry.team.name),
);
const phaseOwnership = computed(() =>
    (session.value?.playbook?.structured_phases ?? []).map((phase) => ({
        ...phase,
        primary: session.value.teams.find((team) =>
            team.responsibility_assignments?.some(
                (assignment) =>
                    assignment.playbook_phase_key === phase.key &&
                    assignment.role === "primary",
            ),
        )?.name,
        supports: session.value.teams
            .filter((team) =>
                team.responsibility_assignments?.some(
                    (assignment) =>
                        assignment.playbook_phase_key === phase.key &&
                        assignment.role === "support",
                ),
            )
            .map((team) => team.name),
    })),
);

const advanceLabel = computed(() => {
    return isFinalInject.value
        ? "Selesaikan Exercise & Buka Debrief"
        : "Lanjut ke Inject Berikutnya";
});

const performAdvance = async () => {
    if (!canAdvance.value) return;
    showAdvanceWarning.value = false;
    advancing.value = true;

    try {
        await axios.post(
            route("tenant.ttx.sessions.advance", props.sessionId),
            {
                confirm_incomplete: missingTeamCount.value > 0,
            },
        );
        toast.success("Inject berhasil dilanjutkan.");
        await fetchSession(true);
        // Select new active inject, or most recent locked if debrief
        nextTick(() => {
            if (activeInject.value) {
                selectedInjectId.value = activeInject.value.id;
            } else if (isDebrief.value) {
                // Select the most recently locked inject
                const locked = sortedInjects.value.filter(
                    (i) => i.status === "locked",
                );
                if (locked.length) {
                    selectedInjectId.value = locked[locked.length - 1].id;
                }
            }
        });
    } catch (err) {
        const status = err.response?.status;
        const msg = err.response?.data?.message ?? "Gagal melanjutkan inject.";
        if (status === 403) {
            accessDeniedError.value =
                "Anda tidak memiliki izin untuk melakukan tindakan ini.";
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

const handleAdvance = () => {
    if (!canAdvance.value) return;
    if (missingTeamCount.value > 0) {
        showAdvanceWarning.value = true;
        return;
    }
    performAdvance();
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
        const { data } = await axios.get(
            route("tenant.ttx.sessions.show", props.sessionId),
        );
        session.value = data;

        // Auto-select: prefer current active inject, else first inject
        if (selectedInjectId.value === null) {
            const active = data.injects?.find((i) => i.status === "active");
            selectedInjectId.value =
                active?.id ?? data.injects?.[0]?.id ?? null;
        }
    } catch (err) {
        error.value = err.response?.data?.message ?? "Gagal memuat data sesi.";
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const handleRefresh = () => fetchSession(true);

const handleSelect = (injectId) => {
    if (injectId === selectedInjectId.value) return;
    selectedInjectId.value = injectId;
};

// --- Response field labels ---
const responseFields = computed(() =>
    session.value?.response_contract_version >= 2
        ? [
              { key: "decision", label: "Keputusan Tim" },
              { key: "rationale", label: "Alasan Keputusan" },
              { key: "immediate_actions", label: "Tindakan Segera" },
              { key: "coordination_handoff", label: "Koordinasi / Handoff" },
          ]
        : [
              { key: "decision", label: "Keputusan" },
              { key: "rationale", label: "Rationale" },
              { key: "owner", label: "Penanggung Jawab" },
              { key: "immediate_actions", label: "Tindakan Segera" },
              { key: "escalation", label: "Eskalasi" },
              { key: "unknowns", label: "Yang Belum Diketahui" },
              { key: "notes", label: "Catatan Tim" },
          ],
);

const formatDate = (iso) => {
    if (!iso) return "-";
    try {
        return new Date(iso).toLocaleString("id-ID", {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch {
        return iso;
    }
};

// --- Lifecycle ---
onMounted(() => fetchSession());
</script>

<template>
    <Head title="Facilitator Control Room" />

    <AppLayout title="Facilitator Control Room">
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
            <BaseButton
                variant="secondary"
                size="sm"
                class="mt-3"
                @click="fetchSession"
            >
                Coba Lagi
            </BaseButton>
        </BaseAlert>

        <!-- Main content -->
        <template v-else-if="session">
            <!-- Header -->
            <div class="console-header">
                <div class="console-header-main">
                    <p class="inject-card-order">Tabletop / Control Room</p>
                    <Link
                        :href="route('tenant.ttx.sessions.index')"
                        class="console-back"
                    >
                        <svg
                            class="h-4 w-4"
                            aria-hidden="true"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Kembali
                    </Link>
                    <h1 class="font-display text-xl font-bold t-ink">
                        {{ session.title }}
                    </h1>
                    <p class="mt-1 text-sm t-muted">
                        Skenario: {{ session.scenario || "-" }} ·
                        {{ session.participant_count }} peserta ·
                        {{ session.teams?.length || 0 }} tim
                    </p>
                    <div class="console-header-meta">
                        <BaseBadge :variant="statusVariant">{{
                            statusLabel
                        }}</BaseBadge>
                        <span class="t-muted text-sm">
                            Inject {{ session.progress?.current ?? "-" }} dari
                            {{ session.progress?.total ?? "-" }}
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
                    <svg
                        class="h-4 w-4"
                        aria-hidden="true"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
                    Segarkan
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
                Persiapan sesi harus diselesaikan terlebih dahulu oleh Tenant
                Admin sebelum exercise dapat dimulai.
            </BaseAlert>

            <!-- READY state: Start action -->
            <BaseAlert
                v-if="isReady"
                variant="info"
                title="Siap untuk dimulai"
                class="mb-6"
            >
                <p>
                    Semua persiapan telah selesai. Tekan tombol di bawah untuk
                    memulai exercise.
                </p>
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
                title="Exercise telah memasuki tahap debrief."
                class="mb-6"
            >
                <p>
                    Timeline dan respons sudah terkunci. Lanjutkan ke evaluasi
                    kualitatif dan After-Action Summary.
                </p>
                <Link
                    :href="
                        route('tenant.ttx.sessions.debrief', props.sessionId)
                    "
                    class="inline-flex mt-3"
                >
                    <BaseButton variant="primary">Buka Debrief</BaseButton>
                </Link>
            </BaseAlert>

            <!-- COMPLETED banner -->
            <BaseAlert
                v-if="isCompleted"
                variant="success"
                title="Exercise telah selesai."
                class="mb-6"
            >
                <p>Sesi ini sudah selesai dan tidak dapat diubah lagi.</p>
                <Link
                    :href="route('tenant.ttx.sessions.result', props.sessionId)"
                    class="inline-flex mt-3"
                >
                    <BaseButton variant="secondary">Lihat Hasil</BaseButton>
                </Link>
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
                    <details class="reference-section">
                        <summary>Scenario & Objectives</summary>
                        <h3>{{ session.scenario }}</h3>
                        <p>{{ session.scenario_snapshot?.scenario }}</p>
                        <p class="whitespace-pre-wrap">
                            {{ session.scenario_snapshot?.objectives }}
                        </p>
                        <ul>
                            <li
                                v-for="capability in session.capabilities"
                                :key="capability.code"
                            >
                                <strong
                                    >{{ capability.code }} /
                                    {{ capability.label }}</strong
                                >
                                <p>{{ capability.description }}</p>
                            </li>
                        </ul>
                    </details>
                    <details v-if="session.playbook" class="reference-section">
                        <summary>Playbook Baseline</summary>
                        <h3>{{ session.playbook.title }}</h3>
                        <p>{{ session.playbook.description }}</p>
                        <ol v-if="session.playbook.structured_phases?.length">
                            <li
                                v-for="phase in session.playbook
                                    .structured_phases"
                                :key="phase.key"
                            >
                                <strong>{{ phase.title }}</strong>
                                <p>{{ phase.guidance }}</p>
                                <small>{{
                                    phase.capability_codes.join(" · ")
                                }}</small>
                            </li>
                        </ol>
                        <p v-else class="whitespace-pre-wrap">
                            {{ session.playbook.content }}
                        </p>
                    </details>
                    <details class="reference-section">
                        <summary>Responsibility Ownership</summary>
                        <ul>
                            <li
                                v-for="phase in phaseOwnership"
                                :key="phase.key"
                            >
                                <strong>{{ phase.title }}</strong>
                                <p>
                                    Primary:
                                    {{ phase.primary || "Belum ditetapkan" }}
                                </p>
                                <p>
                                    Support:
                                    {{ phase.supports.join(", ") || "—" }}
                                </p>
                            </li>
                        </ul>
                    </details>
                    <details class="reference-section">
                        <summary>Teams & Roster</summary>
                        <ul>
                            <li v-for="team in session.teams" :key="team.id">
                                <strong>{{ team.name }}</strong>
                                <p>
                                    {{
                                        team.participants
                                            .map(
                                                (participant) =>
                                                    participant.name,
                                            )
                                            .join(", ") || "Tanpa peserta"
                                    }}
                                </p>
                                <p
                                    v-if="team.responsibilities"
                                    class="whitespace-pre-wrap"
                                >
                                    Catatan persiapan:
                                    {{ team.responsibilities }}
                                </p>
                            </li>
                        </ul>
                    </details>
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
                                    <div class="inject-card-order">
                                        Injeksi #{{ selectedInject.order }}
                                    </div>
                                    <h2 class="inject-card-title">
                                        {{
                                            selectedInject.snapshot?.title ??
                                            `Injeksi #${selectedInject.order}`
                                        }}
                                    </h2>
                                </div>
                                <BaseBadge
                                    :variant="
                                        selectedInject.status === 'active'
                                            ? 'warning'
                                            : selectedInject.status === 'locked'
                                              ? 'success'
                                              : 'neutral'
                                    "
                                >
                                    {{
                                        selectedInject.status === "active"
                                            ? "Aktif"
                                            : selectedInject.status === "locked"
                                              ? "Selesai"
                                              : "Pending"
                                    }}
                                </BaseBadge>
                            </div>

                            <div
                                v-if="selectedInject.snapshot?.description"
                                class="inject-card-body"
                            >
                                <RichContent
                                    :html="selectedInject.snapshot.description"
                                />
                            </div>

                            <!-- Response section: only for Active/Locked injects -->
                            <template
                                v-if="selectedInject.status !== 'pending'"
                            >
                                <div class="inject-card-divider"></div>
                                <div class="inject-response">
                                    <h3 class="inject-response-title">
                                        Respons Tim — Injeksi
                                        {{ selectedInject.order }}
                                    </h3>
                                    <p class="response-summary">
                                        {{
                                            selectedInject.response_summary
                                                ?.responded ?? 0
                                        }}
                                        /
                                        {{
                                            selectedInject.response_summary
                                                ?.total ?? 0
                                        }}
                                        tim sudah merespons
                                    </p>

                                    <ul
                                        class="operational-status"
                                        aria-label="Status respons tim"
                                    >
                                        <li
                                            v-for="entry in selectedInject.team_responses"
                                            :key="entry.team.id"
                                        >
                                            <strong>{{
                                                entry.team.name
                                            }}</strong
                                            ><span>{{
                                                entry.response
                                                    ? selectedInject.status ===
                                                      "locked"
                                                        ? "Locked"
                                                        : "Submitted"
                                                    : "Waiting / Not Submitted"
                                            }}</span>
                                        </li>
                                    </ul>
                                    <div class="team-response-list">
                                        <article
                                            v-for="entry in selectedInject.team_responses"
                                            :key="entry.team.id"
                                            class="team-response-card"
                                        >
                                            <div class="team-response-head">
                                                <h4>{{ entry.team.name }}</h4>
                                                <BaseBadge
                                                    :variant="
                                                        entry.response
                                                            ? 'success'
                                                            : 'neutral'
                                                    "
                                                >
                                                    {{
                                                        entry.response
                                                            ? selectedInject.status ===
                                                              "locked"
                                                                ? "Terkunci"
                                                                : "Submitted"
                                                            : "Belum merespons"
                                                    }}
                                                </BaseBadge>
                                            </div>
                                            <div
                                                v-if="entry.response"
                                                class="response-fields"
                                            >
                                                <div
                                                    v-for="field in responseFields"
                                                    :key="field.key"
                                                    class="response-field"
                                                >
                                                    <div
                                                        class="response-field-label"
                                                    >
                                                        {{ field.label }}
                                                    </div>
                                                    <div
                                                        class="response-field-value"
                                                    >
                                                        {{
                                                            entry.response[
                                                                field.key
                                                            ] || "-"
                                                        }}
                                                    </div>
                                                </div>
                                                <div class="response-meta">
                                                    <span
                                                        v-if="
                                                            entry.response
                                                                .last_edited_by_name
                                                        "
                                                        >Terakhir diperbarui
                                                        oleh
                                                        {{
                                                            entry.response
                                                                .last_edited_by_name
                                                        }}
                                                        ·
                                                        {{
                                                            formatDate(
                                                                entry.response
                                                                    .last_edited_at,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        >Dikirim:
                                                        {{
                                                            formatDate(
                                                                entry.response
                                                                    .submitted_at,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="
                                                            entry.response
                                                                .locked_at
                                                        "
                                                        >Dikunci:
                                                        {{
                                                            formatDate(
                                                                entry.response
                                                                    .locked_at,
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <p v-else class="no-response-copy">
                                                Tim belum mengirim respons untuk
                                                injeksi ini.
                                            </p>
                                        </article>
                                    </div>

                                    <BaseAlert
                                        v-if="selectedInject.legacy_response"
                                        variant="info"
                                        title="Respons historis bersama"
                                        class="mt-4"
                                    >
                                        Data lama ini tidak diatribusikan ke tim
                                        karena kepemilikannya ambigu.
                                        <p class="mt-2">
                                            {{
                                                selectedInject.legacy_response
                                                    .decision
                                            }}
                                        </p>
                                    </BaseAlert>

                                    <!-- Advance action (in_progress only) -->
                                    <div
                                        v-if="
                                            isInProgress &&
                                            selectedInject.status === 'active'
                                        "
                                        class="advance-section"
                                    >
                                        <div class="advance-hint">
                                            Respons tim ditampilkan baca-saja.
                                            Peserta mengelola respons melalui
                                            workspace mereka.
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
                                </div>
                            </template>
                        </div>
                    </template>
                </main>
            </div>
        </template>

        <Modal
            :show="showAdvanceWarning"
            max-width="md"
            @close="showAdvanceWarning = false"
        >
            <div class="advance-modal">
                <h2 class="font-display text-xl font-bold t-ink">
                    Lanjutkan meski respons belum lengkap?
                </h2>
                <p class="mt-3 t-muted">
                    {{ missingTeamCount }} dari {{ responseSummary.total }} tim
                    belum mengirim respons. Jika dilanjutkan, injeksi ini akan
                    dikunci.
                </p>
                <ul class="missing-teams">
                    <li v-for="team in missingTeams" :key="team">{{ team }}</li>
                </ul>
                <div class="advance-modal-actions">
                    <BaseButton
                        variant="secondary"
                        :disabled="advancing"
                        @click="showAdvanceWarning = false"
                        >Batal</BaseButton
                    >
                    <BaseButton
                        variant="primary"
                        :loading="advancing"
                        :disabled="advancing"
                        @click="performAdvance"
                    >
                        Tetap Lanjutkan
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

.response-summary {
    margin: 0 0 var(--sp-4);
    color: var(--muted);
    font-size: 0.875rem;
}
.team-response-list {
    display: grid;
    gap: var(--sp-4);
}
.team-response-card {
    padding: var(--sp-4);
    border: 1px solid var(--line);
    border-radius: var(--r-xl);
    background: var(--surface-2);
}
.team-response-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-3);
    margin-bottom: var(--sp-3);
}
.team-response-head h4 {
    margin: 0;
    color: var(--ink);
    font-weight: 700;
}
.no-response-copy {
    margin: 0;
    color: var(--muted);
    font-size: 0.875rem;
}
.advance-modal {
    padding: var(--sp-6);
}
.advance-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--sp-3);
    margin-top: var(--sp-6);
}

/* Skeleton */
.skeleton {
    background: linear-gradient(
        90deg,
        var(--skeleton-base) 25%,
        var(--skeleton-shine) 50%,
        var(--skeleton-base) 75%
    );
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
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

/* The current situation leads; references stay in a quiet context rail. */
.console-header {
    padding-bottom: var(--sp-5);
    border-bottom: 1px solid var(--line);
}
.console-grid {
    grid-template-columns: minmax(0, 2.2fr) minmax(16rem, 1fr);
}
.console-main {
    grid-column: 1;
    grid-row: 1;
    min-width: 0;
}
.console-sidebar {
    grid-column: 2;
    grid-row: 1;
    min-width: 0;
    padding-left: var(--sp-5);
    border-left: 1px solid var(--line);
}
.inject-card {
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}
.inject-card-head,
.inject-card-body,
.inject-response {
    padding-inline: 0;
}
.team-response-card {
    border-radius: var(--r-lg);
    background: var(--surface);
}
.response-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.response-meta {
    grid-column: 1/-1;
}
.operational-status {
    list-style: none;
    padding: 0;
    margin: var(--sp-4) 0 var(--sp-6);
    border-top: 1px solid var(--line);
}
.operational-status li {
    display: flex;
    justify-content: space-between;
    gap: var(--sp-3);
    padding: var(--sp-3) 0;
    border-bottom: 1px solid var(--line);
    color: var(--ink);
    font-size: 0.875rem;
}
.operational-status span {
    color: var(--muted);
}
.reference-section {
    padding: var(--sp-4) 0;
    border-bottom: 1px solid var(--line);
}
.reference-section summary {
    cursor: pointer;
    color: var(--ink);
    font-size: 0.875rem;
    font-weight: 700;
    transition: color 160ms ease;
}
.reference-section summary:hover {
    color: var(--brand);
}
.reference-section summary:active {
    transform: translateY(1px);
}
.reference-section h3,
.reference-section ul,
.reference-section ol {
    margin-top: var(--sp-3);
}
.reference-section ul,
.reference-section ol {
    list-style: none;
    padding: 0;
}
.reference-section li {
    padding: var(--sp-3) 0;
    border-top: 1px solid var(--line);
}
.reference-section strong,
.reference-section h3 {
    color: var(--ink);
    font-size: 0.825rem;
}
.reference-section p,
.reference-section small {
    margin-top: var(--sp-2);
    color: var(--muted);
    font-size: 0.8rem;
    line-height: 1.65;
    overflow-wrap: anywhere;
}
.missing-teams {
    padding-left: 1.25rem;
    margin-top: var(--sp-3);
    color: var(--ink);
}
.console-sidebar :deep(.timeline-btn--selected) {
    box-shadow: none;
    background: var(--surface-2);
}
.console-sidebar :deep(.timeline-title) {
    white-space: normal;
    overflow-wrap: anywhere;
}
.console-sidebar :deep(.timeline-btn:active) {
    transform: translateY(1px);
}
@media (max-width: 900px) {
    .console-grid {
        grid-template-columns: 1fr;
    }
    .console-main {
        grid-column: 1;
        grid-row: 1;
    }
    .console-sidebar {
        grid-column: 1;
        grid-row: 2;
        position: static;
        padding-left: 0;
        border-left: 0;
        border-top: 1px solid var(--line);
    }
    .console-sidebar :deep(.timeline-list) {
        flex-direction: column;
        overflow-x: visible;
    }
    .console-sidebar :deep(.timeline-item) {
        min-width: 0;
    }
}
@media (max-width: 639px) {
    .response-fields {
        grid-template-columns: 1fr;
    }
    .team-response-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
