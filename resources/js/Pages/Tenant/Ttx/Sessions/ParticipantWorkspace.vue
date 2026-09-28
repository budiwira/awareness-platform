<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseAlert from "@/Components/BaseAlert.vue";
import BaseBadge from "@/Components/BaseBadge.vue";
import BaseButton from "@/Components/BaseButton.vue";
import EmptyState from "@/Components/EmptyState.vue";
import ResponseEditor from "./Partials/ResponseEditor.vue";
import { useToast } from "@/Composables/useToast";

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
    resultMode: { type: Boolean, default: false },
});
const session = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const saving = ref(false);
const error = ref(null);
const selectedInjectId = ref(null);
const editorRef = ref(null);
const editorKey = ref(0);
const saveMessage = ref(null);
const lastSavedAt = ref(null);
const briefingEntered = ref(false);
const toast = useToast();
const sortedInjects = computed(() =>
    [...(session.value?.injects ?? [])].sort((a, b) => a.order - b.order),
);
const activeInject = computed(
    () =>
        sortedInjects.value.find((inject) => inject.status === "active") ??
        null,
);
const selectedInject = computed(
    () =>
        sortedInjects.value.find(
            (inject) => inject.id === selectedInjectId.value,
        ) ?? null,
);
const isReady = computed(() => session.value?.status === "ready");
const isInProgress = computed(() => session.value?.status === "in_progress");
const isDebrief = computed(() => session.value?.status === "debrief");
const isCompleted = computed(() => session.value?.status === "completed");
const statusLabel = computed(
    () =>
        ({
            ready: "Siap",
            in_progress: "Berlangsung",
            debrief: "Menunggu Review",
            completed: "Selesai",
        })[session.value?.status] ?? session.value?.status,
);
const statusVariant = computed(
    () =>
        ({
            ready: "info",
            in_progress: "warning",
            debrief: "brand",
            completed: "success",
        })[session.value?.status] ?? "neutral",
);
const ownTeam = computed(() => session.value?.teams?.[0] ?? null);
const playbookPhases = computed(
    () => session.value?.playbook?.structured_phases ?? [],
);
const primaryPhases = computed(() =>
    playbookPhases.value.filter((phase) => phase.team_role === "primary"),
);
const supportPhases = computed(() =>
    playbookPhases.value.filter((phase) => phase.team_role === "support"),
);
const knownFacts = computed(() => {
    const facts = selectedInject.value?.snapshot?.known_facts;
    return Array.isArray(facts)
        ? facts.filter(Boolean)
        : typeof facts === "string" && facts.trim()
          ? [facts.trim()]
          : [];
});
const progressItems = computed(() =>
    sortedInjects.value.map((inject) => ({
        order: inject.order,
        state: inject.status === "active" ? "Current" : "Complete",
    })),
);

function selectDefaultInject() {
    if (
        !sortedInjects.value.some(
            (inject) => inject.id === selectedInjectId.value,
        )
    )
        selectedInjectId.value =
            activeInject.value?.id ?? sortedInjects.value.at(-1)?.id ?? null;
}
async function fetchSession({ refresh = false, resetEditor = false } = {}) {
    if (refresh) refreshing.value = true;
    else loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(
            route("tenant.ttx.sessions.show", props.sessionId),
        );
        session.value = data;
        selectDefaultInject();
        if (resetEditor) editorKey.value += 1;
        await nextTick();
        editorRef.value?.clearErrors();
    } catch (failure) {
        error.value =
            failure.response?.status === 403
                ? "Anda tidak memiliki akses ke Exercise Room ini."
                : (failure.response?.data?.message ??
                  "Data exercise tidak dapat dimuat.");
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
}
function handleRefresh() {
    if (refreshing.value || saving.value) return;
    if (editorRef.value?.isDirty) {
        editorRef.value?.setGeneralError(
            "Simpan keputusan atau muat versi tim terbaru sebelum menyegarkan halaman.",
        );
        return;
    }
    fetchSession({ refresh: true, resetEditor: true });
}
function handleSelect(injectId) {
    if (injectId === selectedInjectId.value) return;
    if (editorRef.value?.isDirty) {
        editorRef.value?.setGeneralError(
            "Simpan keputusan sebelum berpindah situation update.",
        );
        return;
    }
    selectedInjectId.value = injectId;
    saveMessage.value = null;
    lastSavedAt.value = null;
    editorKey.value += 1;
}
const responsePayload = (draft) => ({
    decision: draft.decision,
    rationale: draft.rationale || null,
    owner: draft.owner || null,
    immediate_actions: draft.immediate_actions || null,
    coordination_handoff: draft.coordination_handoff || null,
    escalation: draft.escalation || null,
    unknowns: draft.unknowns || null,
    notes: draft.notes || null,
});
async function handleSave({ isCreate, draft, expectedRevision }) {
    if (
        saving.value ||
        !selectedInject.value ||
        selectedInject.value.status !== "active"
    )
        return;
    saving.value = true;
    saveMessage.value = null;
    editorRef.value?.clearErrors();
    try {
        let saved;
        if (isCreate)
            ({ data: saved } = await axios.post(
                route("tenant.ttx.sessions.responses.store", props.sessionId),
                {
                    session_inject_id: selectedInject.value.id,
                    ...responsePayload(draft),
                },
            ));
        else
            ({ data: saved } = await axios.put(
                route("tenant.ttx.sessions.responses.update", [
                    props.sessionId,
                    selectedInject.value.response.id,
                ]),
                {
                    expected_revision: expectedRevision,
                    ...responsePayload(draft),
                },
            ));
        await fetchSession({ refresh: true, resetEditor: true });
        saveMessage.value = "Keputusan tim berhasil dikirim.";
        lastSavedAt.value =
            saved.updated_at ?? saved.submitted_at ?? new Date().toISOString();
        toast.success(saveMessage.value);
    } catch (failure) {
        const status = failure.response?.status;
        const data = failure.response?.data;
        if (status === 409)
            editorRef.value?.setConflictError(
                "Keputusan tim telah diperbarui oleh anggota lain.",
            );
        else if (status === 422)
            editorRef.value?.setValidationError(data?.errors ?? {});
        else if (status === 403)
            editorRef.value?.setAccessDeniedError(
                "Anda tidak memiliki izin untuk mengubah keputusan ini.",
            );
        else
            editorRef.value?.setGeneralError(
                data?.message ?? "Keputusan tidak dapat dikirim.",
            );
    } finally {
        saving.value = false;
    }
}
const loadLatestResponse = () =>
    fetchSession({ refresh: true, resetEditor: true });
onMounted(() => fetchSession());
</script>

<template>
    <Head :title="props.resultMode ? 'Hasil Tabletop' : 'Exercise Room'" />
    <AppLayout :title="props.resultMode ? 'Hasil Tabletop' : 'Exercise Room'">
        <div v-if="loading" class="space-y-6" aria-label="Memuat Exercise Room">
            <div class="skeleton h-28 rounded-xl"></div>
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="skeleton h-96 lg:col-span-2"></div>
                <div class="skeleton h-72"></div>
            </div>
        </div>
        <BaseAlert v-else-if="error" variant="danger" title="Gagal Memuat"
            ><p>{{ error }}</p>
            <BaseButton
                variant="secondary"
                size="sm"
                class="mt-3"
                @click="fetchSession()"
                >Coba Lagi</BaseButton
            ></BaseAlert
        >

        <div v-else-if="session" class="exercise-room fade-in">
            <header class="room-header">
                <div>
                    <p class="eyebrow">Tabletop / Exercise Room</p>
                    <h1
                        class="font-display text-2xl font-bold t-ink sm:text-3xl"
                    >
                        {{ session.title }}
                    </h1>
                    <div class="room-meta">
                        <BaseBadge :variant="statusVariant">{{
                            statusLabel
                        }}</BaseBadge
                        ><span>{{ session.participant_team?.name }}</span
                        ><span>Fasilitator {{ session.facilitator_name }}</span>
                    </div>
                </div>
                <BaseButton
                    variant="secondary"
                    size="sm"
                    :loading="refreshing"
                    :disabled="refreshing || saving"
                    @click="handleRefresh"
                    >Segarkan</BaseButton
                >
            </header>

            <main
                v-if="isReady"
                class="briefing"
                aria-labelledby="briefing-title"
            >
                <div v-if="!briefingEntered">
                    <p class="eyebrow">Exercise Briefing</p>
                    <h2 id="briefing-title" class="briefing-title">
                        {{ session.scenario }}
                    </h2>
                    <p class="briefing-lead">
                        {{ session.scenario_snapshot?.scenario }}
                    </p>
                    <section>
                        <h3>Purpose</h3>
                        <p class="whitespace-pre-wrap">
                            {{
                                session.scenario_snapshot?.objectives ||
                                "Menguji pengambilan keputusan dan koordinasi respons organisasi."
                            }}
                        </p>
                    </section>
                    <section>
                        <h3>Exercise facts</h3>
                        <dl class="briefing-facts">
                            <div>
                                <dt>Scope / durasi</dt>
                                <dd>
                                    {{
                                        session.scenario_snapshot?.scope ||
                                        "Disampaikan fasilitator"
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt>Tim Anda</dt>
                                <dd>{{ session.participant_team?.name }}</dd>
                            </div>
                            <div>
                                <dt>Situation updates</dt>
                                <dd>Dirilis bertahap oleh fasilitator</dd>
                            </div>
                        </dl>
                    </section>
                    <section>
                        <h3>Your team</h3>
                        <p>
                            {{
                                ownTeam?.participants
                                    ?.map((person) => person.name)
                                    .join(", ") || "Roster tim tidak tersedia."
                            }}
                        </p>
                    </section>
                    <section>
                        <h3>Your mission</h3>
                        <p
                            v-if="session.participant_team?.responsibilities"
                            class="team-notes"
                        >
                            {{ session.participant_team.responsibilities }}
                        </p>
                        <div class="mission-grid">
                            <div>
                                <strong>Primary</strong>
                                <p>
                                    {{
                                        primaryPhases
                                            .map((phase) => phase.title)
                                            .join(", ") ||
                                        "Belum ada area Primary."
                                    }}
                                </p>
                            </div>
                            <div>
                                <strong>Support</strong>
                                <p>
                                    {{
                                        supportPhases
                                            .map((phase) => phase.title)
                                            .join(", ") ||
                                        "Tidak ada penugasan Support."
                                    }}
                                </p>
                            </div>
                        </div>
                    </section>
                    <section>
                        <h3>Capabilities being tested</h3>
                        <ul class="brief-list">
                            <li
                                v-for="capability in session.capabilities"
                                :key="capability.code"
                            >
                                <strong
                                    >{{ capability.code }} /
                                    {{ capability.label }}</strong
                                ><span>{{ capability.description }}</span>
                            </li>
                        </ul>
                    </section>
                    <section v-if="session.playbook">
                        <h3>Playbook reference</h3>
                        <p>
                            {{ session.playbook.title }} —
                            {{ session.playbook.description }}
                        </p>
                        <ol class="brief-list">
                            <li
                                v-for="phase in playbookPhases"
                                :key="phase.key"
                            >
                                <strong
                                    >{{ phase.title
                                    }}<span
                                        v-if="phase.team_role"
                                        class="role-mark"
                                        >{{
                                            phase.team_role === "primary"
                                                ? "Primary"
                                                : "Support"
                                        }}</span
                                    ></strong
                                ><span>{{ phase.participant_summary }}</span>
                            </li>
                        </ol>
                    </section>
                    <section>
                        <h3>What happens next?</h3>
                        <p>
                            Fasilitator akan merilis satu situation update pada
                            satu waktu. Tim mendiskusikan situasi, mengirim satu
                            keputusan resmi, lalu menunggu perkembangan
                            berikutnya.
                        </p>
                    </section>
                    <BaseButton
                        variant="primary"
                        @click="briefingEntered = true"
                        >Enter Exercise</BaseButton
                    >
                </div>
                <BaseAlert v-else variant="info" title="Briefing selesai"
                    ><p>
                        Menunggu fasilitator memulai exercise. Tidak ada
                        situation update yang dibuka sebelum exercise dimulai.
                    </p>
                    <BaseButton
                        variant="secondary"
                        size="sm"
                        class="mt-3"
                        @click="briefingEntered = false"
                        >Baca Ulang Briefing</BaseButton
                    ></BaseAlert
                >
            </main>

            <BaseAlert
                v-else-if="isDebrief"
                variant="info"
                title="Exercise menunggu review"
                >Keputusan tim telah dikunci. Fasilitator sedang menyiapkan
                evaluasi dan ringkasan akhir.</BaseAlert
            >
            <BaseAlert
                v-else-if="isCompleted"
                variant="success"
                title="Exercise telah selesai"
                >Timeline yang dirilis tersedia dalam mode baca-saja. Ringkasan
                akhir yang aman ditampilkan di bawah.</BaseAlert
            >

            <section
                v-if="isCompleted && session.outcome"
                class="result-section"
            >
                <p class="eyebrow">Hasil Akhir</p>
                <h2 class="font-display text-xl font-bold t-ink">
                    After-Action Summary
                </h2>
                <div class="outcome-grid">
                    <div>
                        <h3>Ringkasan Keseluruhan</h3>
                        <p>{{ session.outcome.overall_summary }}</p>
                    </div>
                    <div>
                        <h3>Kekuatan</h3>
                        <p>{{ session.outcome.strengths }}</p>
                    </div>
                    <div>
                        <h3>Area Perbaikan</h3>
                        <p>{{ session.outcome.improvement_areas }}</p>
                    </div>
                    <div>
                        <h3>Pelajaran Utama</h3>
                        <p>{{ session.outcome.key_lessons }}</p>
                    </div>
                </div>
            </section>

            <template v-if="!isReady && sortedInjects.length">
                <nav
                    class="progress-rail"
                    aria-label="Progress situation update"
                >
                    <ol>
                        <li
                            v-for="item in progressItems"
                            :key="item.order"
                            :class="item.state.toLowerCase()"
                        >
                            <span>{{
                                String(item.order).padStart(2, "0")
                            }}</span
                            >{{ item.state }}
                        </li>
                    </ol>
                </nav>
                <div class="room-layout">
                    <main v-if="selectedInject" class="room-main">
                        <section class="situation">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">
                                        Situation Update
                                        {{
                                            String(
                                                selectedInject.order,
                                            ).padStart(2, "0")
                                        }}
                                    </p>
                                    <h2>
                                        {{
                                            selectedInject.snapshot?.title ??
                                            `Update ${selectedInject.order}`
                                        }}
                                    </h2>
                                </div>
                                <BaseBadge
                                    :variant="
                                        selectedInject.status === 'active'
                                            ? 'warning'
                                            : 'success'
                                    "
                                    >{{
                                        selectedInject.status === "active"
                                            ? "Current"
                                            : "Complete"
                                    }}</BaseBadge
                                >
                            </div>
                            <p class="situation-copy whitespace-pre-wrap">
                                {{
                                    selectedInject.snapshot?.situation ??
                                    selectedInject.snapshot?.description
                                }}
                            </p>
                            <ul v-if="knownFacts.length" class="facts-list">
                                <li v-for="fact in knownFacts" :key="fact">
                                    {{ fact }}
                                </li>
                            </ul>
                            <div
                                v-if="
                                    selectedInject.snapshot?.discussion_prompt
                                "
                                class="discussion"
                            >
                                <strong>Pertanyaan untuk tim</strong>
                                <p>
                                    {{
                                        selectedInject.snapshot
                                            .discussion_prompt
                                    }}
                                </p>
                            </div>
                        </section>
                        <section class="composer">
                            <div class="section-heading">
                                <div>
                                    <p class="eyebrow">
                                        Team Decision Composer
                                    </p>
                                    <h2>
                                        Keputusan
                                        {{ session.participant_team?.name }}
                                    </h2>
                                </div>
                                <span
                                    v-if="selectedInject.status === 'active'"
                                    class="editable"
                                    >Dapat diedit</span
                                >
                            </div>
                            <ResponseEditor
                                :key="`${selectedInject.id}-${editorKey}`"
                                ref="editorRef"
                                :inject-status="selectedInject.status"
                                :inject-id="selectedInject.id"
                                :response="selectedInject.response"
                                :contract-version="
                                    session.response_contract_version
                                "
                                :team-name="session.participant_team?.name"
                                :saving="saving"
                                :save-message="saveMessage"
                                :last-saved-at="lastSavedAt"
                                @save="handleSave"
                                @discard="loadLatestResponse"
                            />
                        </section>
                    </main>
                    <aside class="context-rail">
                        <section>
                            <p class="eyebrow">Team Context</p>
                            <h2>{{ session.participant_team?.name }}</h2>
                            <p
                                v-if="
                                    session.participant_team?.responsibilities
                                "
                                class="team-notes"
                            >
                                {{ session.participant_team.responsibilities }}
                            </p>
                            <dl class="ownership-list">
                                <div>
                                    <dt>Primary</dt>
                                    <dd>
                                        {{
                                            primaryPhases
                                                .map((phase) => phase.title)
                                                .join(", ") || "—"
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Support</dt>
                                    <dd>
                                        {{
                                            supportPhases
                                                .map((phase) => phase.title)
                                                .join(", ") || "—"
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <details
                            v-if="session.playbook"
                            class="playbook-reference"
                        >
                            <summary>Playbook Reference</summary>
                            <p>{{ session.playbook.title }}</p>
                            <ol>
                                <li
                                    v-for="phase in playbookPhases"
                                    :key="phase.key"
                                >
                                    <strong>{{ phase.title }}</strong
                                    ><span>{{ phase.participant_summary }}</span
                                    ><em v-if="phase.team_role">{{
                                        phase.team_role === "primary"
                                            ? "Primary"
                                            : "Support"
                                    }}</em>
                                </li>
                            </ol>
                        </details>
                        <section class="released-list">
                            <h3>Released updates</h3>
                            <button
                                v-for="inject in sortedInjects"
                                :key="inject.id"
                                :class="{
                                    selected: inject.id === selectedInjectId,
                                }"
                                @click="handleSelect(inject.id)"
                            >
                                <span>{{
                                    String(inject.order).padStart(2, "0")
                                }}</span
                                >{{
                                    inject.status === "active"
                                        ? "Current update"
                                        : "Completed update"
                                }}
                            </button>
                        </section>
                    </aside>
                </div>
            </template>
            <EmptyState
                v-else-if="isInProgress && !sortedInjects.length"
                title="Belum ada situation update"
                message="Update akan muncul setelah dirilis oleh fasilitator."
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.exercise-room {
    display: flex;
    flex-direction: column;
    gap: var(--sp-6);
    min-width: 0;
}
.room-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-5);
    padding-bottom: var(--sp-5);
    border-bottom: 1px solid var(--line);
}
.eyebrow {
    margin: 0 0 var(--sp-1);
    color: var(--brand);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.room-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--sp-3);
    margin-top: var(--sp-3);
    color: var(--muted);
    font-size: 0.875rem;
}
.briefing {
    max-width: 52rem;
}
.briefing-title {
    color: var(--ink);
    font-family: var(--font-sans);
    font-size: 2rem;
    font-weight: 700;
}
.briefing-lead {
    margin-top: var(--sp-3);
    color: var(--muted);
    font-size: 1.05rem;
    line-height: 1.75;
}
.briefing section {
    padding: var(--sp-6) 0;
    border-top: 1px solid var(--line);
}
.briefing section h3 {
    margin-bottom: var(--sp-3);
    color: var(--ink);
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.briefing section p {
    color: var(--muted);
    line-height: 1.7;
}
.briefing-facts,
.mission-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--sp-5);
}
.briefing-facts dt,
.ownership-list dt {
    color: var(--muted);
    font-size: 0.75rem;
    text-transform: uppercase;
}
.briefing-facts dd,
.ownership-list dd {
    margin-top: 0.3rem;
    color: var(--ink);
}
.mission-grid strong {
    color: var(--ink);
}
.brief-list {
    display: grid;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    border-top: 1px solid var(--line);
}
.brief-list li {
    display: grid;
    gap: 0.3rem;
    padding: var(--sp-3) 0;
    border-bottom: 1px solid var(--line);
}
.brief-list strong {
    color: var(--ink);
}
.brief-list span {
    color: var(--muted);
    line-height: 1.55;
}
.role-mark {
    display: inline-block;
    margin-left: var(--sp-2);
    color: var(--brand) !important;
    font-size: 0.7rem;
    text-transform: uppercase;
}
.progress-rail {
    padding: var(--sp-3) 0;
    border-bottom: 1px solid var(--line);
    overflow-x: auto;
}
.progress-rail ol {
    display: flex;
    gap: var(--sp-5);
    min-width: max-content;
    margin: 0;
    padding: 0;
    list-style: none;
}
.progress-rail li {
    display: flex;
    align-items: center;
    gap: var(--sp-2);
    color: var(--muted);
    font-size: 0.8rem;
}
.progress-rail li span {
    font-variant-numeric: tabular-nums;
}
.progress-rail .current {
    color: var(--brand);
    font-weight: 700;
}
.room-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) minmax(16rem, 1fr);
    gap: var(--sp-8);
    align-items: start;
}
.room-main {
    display: flex;
    flex-direction: column;
    gap: var(--sp-8);
    min-width: 0;
}
.situation,
.composer {
    min-width: 0;
}
.composer {
    padding: var(--sp-6);
    border: 1px solid var(--line);
    background: var(--surface);
}
.section-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-4);
    padding-bottom: var(--sp-4);
    border-bottom: 1px solid var(--line);
}
.section-heading h2,
.context-rail h2 {
    color: var(--ink);
    font-family: var(--font-sans);
    font-size: 1.3rem;
    font-weight: 700;
}
.situation-copy {
    max-width: 46rem;
    margin-top: var(--sp-5);
    color: var(--ink);
    font-size: 1rem;
    line-height: 1.8;
    overflow-wrap: anywhere;
}
.facts-list {
    margin-top: var(--sp-4);
    padding-left: 1.25rem;
    color: var(--muted);
    line-height: 1.7;
}
.discussion {
    margin-top: var(--sp-5);
    padding: var(--sp-4);
    border-left: 3px solid var(--brand);
    background: var(--surface-2);
}
.discussion strong {
    color: var(--ink);
}
.discussion p {
    margin-top: var(--sp-2);
    color: var(--muted);
    line-height: 1.65;
}
.editable {
    color: var(--ok);
    font-size: 0.75rem;
    font-weight: 700;
}
.composer :deep(.response-editor) {
    margin-top: var(--sp-5);
}
.context-rail {
    position: sticky;
    top: var(--sp-5);
    display: flex;
    flex-direction: column;
    gap: var(--sp-6);
    min-width: 0;
    padding-left: var(--sp-6);
    border-left: 1px solid var(--line);
}
.team-notes {
    margin-top: var(--sp-3);
    color: var(--muted);
    line-height: 1.6;
    white-space: pre-wrap;
}
.ownership-list {
    display: grid;
    gap: var(--sp-4);
    margin-top: var(--sp-5);
}
.playbook-reference {
    padding: var(--sp-4) 0;
    border-block: 1px solid var(--line);
}
.playbook-reference summary {
    cursor: pointer;
    color: var(--ink);
    font-weight: 700;
}
.playbook-reference summary:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 3px;
}
.playbook-reference > p {
    margin-top: var(--sp-3);
    color: var(--muted);
    font-size: 0.875rem;
}
.playbook-reference ol {
    display: grid;
    gap: var(--sp-3);
    margin-top: var(--sp-4);
    padding: 0;
    list-style: none;
}
.playbook-reference li {
    display: grid;
    gap: 0.25rem;
}
.playbook-reference strong {
    color: var(--ink);
    font-size: 0.875rem;
}
.playbook-reference span {
    color: var(--muted);
    font-size: 0.8rem;
    line-height: 1.5;
}
.playbook-reference em {
    color: var(--brand);
    font-size: 0.72rem;
    font-style: normal;
    text-transform: uppercase;
}
.released-list {
    display: grid;
    gap: var(--sp-2);
}
.released-list h3 {
    color: var(--ink);
    font-size: 0.8rem;
    text-transform: uppercase;
}
.released-list button {
    display: flex;
    gap: var(--sp-3);
    width: 100%;
    padding: var(--sp-2);
    border-left: 2px solid var(--line);
    color: var(--muted);
    font-size: 0.825rem;
    text-align: left;
    transition:
        color 160ms ease,
        border-color 160ms ease,
        background 160ms ease;
}
.released-list button:hover,
.released-list button.selected {
    border-color: var(--brand);
    background: var(--surface-2);
    color: var(--ink);
}
.released-list button:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}
.result-section {
    padding: var(--sp-6);
    border: 1px solid var(--line);
    background: var(--surface);
}
.outcome-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--sp-5);
    margin-top: var(--sp-5);
}
.outcome-grid h3 {
    color: var(--ink);
    font-size: 0.875rem;
    font-weight: 700;
}
.outcome-grid p {
    margin-top: var(--sp-2);
    color: var(--muted);
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.skeleton {
    background: var(--surface-2);
}
@media (max-width: 768px) {
    .room-header {
        align-items: stretch;
        flex-direction: column;
    }
    .room-layout {
        grid-template-columns: 1fr;
    }
    .context-rail {
        position: static;
        padding: var(--sp-6) 0 0;
        border-top: 1px solid var(--line);
        border-left: 0;
    }
    .briefing-facts,
    .mission-grid,
    .outcome-grid {
        grid-template-columns: 1fr;
    }
    .composer,
    .result-section {
        padding: var(--sp-4);
    }
}
@media (max-width: 420px) {
    .section-heading {
        flex-direction: column;
    }
    .room-meta {
        align-items: flex-start;
        flex-direction: column;
        gap: var(--sp-2);
    }
    .briefing-title {
        font-size: 1.6rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .released-list button {
        transition: none;
    }
}
</style>
