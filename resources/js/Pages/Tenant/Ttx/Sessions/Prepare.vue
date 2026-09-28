<script setup>
import { computed, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import axios from "axios";
import AppLayout from "@/Layouts/AppLayout.vue";
import EmptyState from "@/Components/EmptyState.vue";

const props = defineProps({
    session: Object,
    exercise_title: String,
    exercise_context: Object,
    playbook: Object,
    capabilities: Array,
    relevant_playbook_phase_keys: Array,
    inject_count: Number,
    facilitator: Object,
    participants: Array,
    teams: Array,
    readiness: Object,
    permissions: Object,
    assignable_users: Array,
    can_open_console: Boolean,
});
const state = ref({ ...props });
const busy = ref(false);
const error = ref("");
const notice = ref("");
const teamForm = ref({ name: "", description: "" });
const participantForm = ref({ user_id: "", team_id: "" });
const responsibilityDrafts = ref(
    Object.fromEntries(
        props.teams.map((team) => [team.id, team.responsibilities ?? ""]),
    ),
);

const canEdit = computed(
    () =>
        state.value.session.status === "draft" &&
        state.value.permissions.can_manage_roster,
);
const relevantPhases = computed(() => {
    const keys = new Set(state.value.relevant_playbook_phase_keys ?? []);
    return (state.value.playbook?.structured_phases ?? []).filter((phase) =>
        keys.has(phase.key),
    );
});
const rosterByTeam = computed(() =>
    state.value.teams.map((team) => ({
        ...team,
        participants: state.value.participants.filter(
            (participant) => participant.team_id === team.id,
        ),
    })),
);
const phaseByKey = computed(() =>
    Object.fromEntries(relevantPhases.value.map((phase) => [phase.key, phase])),
);
const readinessChecks = computed(() => [
    { ok: state.value.readiness.has_exercise_snapshot, label: "Skenario siap" },
    {
        ok: state.value.readiness.has_playbook,
        label: "Referensi Playbook siap",
    },
    {
        ok: state.value.readiness.has_facilitator,
        label: "Fasilitator aktif dan memiliki otoritas",
    },
    { ok: state.value.readiness.has_teams, label: "Tim exercise tersedia" },
    {
        ok: true,
        label:
            state.value.capabilities.length > 0
                ? `${state.value.capabilities.length} capability exercise didefinisikan`
                : "Session legacy menggunakan tujuan exercise tanpa metadata capability",
    },
    {
        ok: state.value.readiness.participants_have_valid_team,
        label: "Peserta aktif telah ditempatkan pada satu tim",
    },
    {
        ok:
            state.value.readiness.responsibility_coverage_complete ??
            state.value.readiness.participating_teams_prepared,
        label: state.value.readiness.uses_structured_responsibility_ownership
            ? "Semua area respons memiliki Primary yang berpeserta"
            : "Persiapan tim lengkap",
    },
    {
        ok:
            state.value.readiness.has_injects &&
            state.value.readiness.all_injects_pending,
        label: `${state.value.inject_count} situation update siap`,
    },
]);

function sync(payload) {
    const previousTeams = state.value.teams;
    const previousDrafts = responsibilityDrafts.value;
    state.value = payload;
    responsibilityDrafts.value = Object.fromEntries(
        payload.teams.map((team) => {
            const previous = previousTeams.find((item) => item.id === team.id);
            const hasLocalChanges =
                previous &&
                previousDrafts[team.id] !== (previous.responsibilities ?? "");
            return [
                team.id,
                hasLocalChanges
                    ? previousDrafts[team.id]
                    : (team.responsibilities ?? ""),
            ];
        }),
    );
}
function failureMessage(failure) {
    const errors = failure.response?.data?.errors;
    return errors
        ? Object.values(errors).flat()[0]
        : (failure.response?.data?.message ??
              "Perubahan tidak dapat disimpan.");
}
async function mutate(
    method,
    url,
    data = {},
    success = "Perubahan tersimpan.",
) {
    if (busy.value) return false;
    busy.value = true;
    error.value = "";
    notice.value = "";
    try {
        const response = await axios({
            method,
            url,
            data,
            headers: { Accept: "application/json" },
        });
        sync(response.data);
        notice.value = success;
        return true;
    } catch (failure) {
        error.value = failureMessage(failure);
        return false;
    } finally {
        busy.value = false;
    }
}
async function assignmentRequest(method, assignmentId, data = {}) {
    const url = assignmentId
        ? route(
              `tenant.ttx.sessions.responsibility-assignments.${method === "delete" ? "destroy" : "update"}`,
              [state.value.session.id, assignmentId],
          )
        : route(
              "tenant.ttx.sessions.responsibility-assignments.store",
              state.value.session.id,
          );
    const response = await axios({
        method,
        url,
        data,
        headers: { Accept: "application/json" },
    });
    sync(response.data);
}
function assignmentsFor(phaseKey) {
    return state.value.teams.flatMap((team) =>
        team.responsibility_assignments
            .filter((assignment) => assignment.playbook_phase_key === phaseKey)
            .map((assignment) => ({
                ...assignment,
                team_id: team.id,
                team_name: team.name,
            })),
    );
}
function primaryTeamId(phaseKey) {
    return (
        assignmentsFor(phaseKey).find(
            (assignment) => assignment.role === "primary",
        )?.team_id ?? ""
    );
}
function isSupport(phaseKey, teamId) {
    return assignmentsFor(phaseKey).some(
        (assignment) =>
            assignment.team_id === teamId && assignment.role === "support",
    );
}
async function setPrimary(phaseKey, rawTeamId) {
    if (!canEdit.value || busy.value) return;
    const teamId = Number(rawTeamId) || null;
    const current = assignmentsFor(phaseKey).find(
        (assignment) => assignment.role === "primary",
    );
    if (!teamId) return;
    if ((current?.team_id ?? null) === teamId) return;
    busy.value = true;
    error.value = "";
    notice.value = "";
    try {
        if (current)
            await assignmentRequest("put", current.id, {
                role: "primary",
                team_id: teamId,
            });
        else {
            const support = assignmentsFor(phaseKey).find(
                (assignment) => assignment.team_id === teamId,
            );
            if (support)
                await assignmentRequest("put", support.id, { role: "primary" });
            else
                await assignmentRequest("post", null, {
                    team_id: teamId,
                    playbook_phase_key: phaseKey,
                    role: "primary",
                });
        }
        notice.value = "Primary owner diperbarui.";
    } catch (failure) {
        error.value = failureMessage(failure);
    } finally {
        busy.value = false;
    }
}
async function toggleSupport(phaseKey, teamId, checked) {
    if (!canEdit.value || busy.value) return;
    const existing = assignmentsFor(phaseKey).find(
        (assignment) =>
            assignment.team_id === teamId && assignment.role === "support",
    );
    busy.value = true;
    error.value = "";
    notice.value = "";
    try {
        if (checked && !existing)
            await assignmentRequest("post", null, {
                team_id: teamId,
                playbook_phase_key: phaseKey,
                role: "support",
            });
        if (!checked && existing)
            await assignmentRequest("delete", existing.id);
        notice.value = "Support owner diperbarui.";
    } catch (failure) {
        error.value = failureMessage(failure);
    } finally {
        busy.value = false;
    }
}
async function addTeam() {
    if (
        await mutate(
            "post",
            route("tenant.ttx.sessions.teams.store", state.value.session.id),
            teamForm.value,
            "Tim berhasil dibuat.",
        )
    )
        teamForm.value = { name: "", description: "" };
}
async function assignParticipant() {
    if (
        await mutate(
            "post",
            route(
                "tenant.ttx.sessions.participants.store",
                state.value.session.id,
            ),
            participantForm.value,
            "Peserta berhasil ditugaskan.",
        )
    )
        participantForm.value = { user_id: "", team_id: "" };
}
const removeTeam = (id) =>
    mutate(
        "delete",
        route("tenant.ttx.sessions.teams.destroy", [
            state.value.session.id,
            id,
        ]),
        {},
        "Tim berhasil dihapus.",
    );
const removeParticipant = (id) =>
    mutate(
        "delete",
        route("tenant.ttx.sessions.participants.destroy", [
            state.value.session.id,
            id,
        ]),
        {},
        "Peserta berhasil dihapus.",
    );
async function saveNotes(id) {
    const submitted = responsibilityDrafts.value[id];
    if (
        await mutate(
            "put",
            route("tenant.ttx.sessions.teams.responsibilities.update", [
                state.value.session.id,
                id,
            ]),
            { responsibilities: submitted },
            "Catatan persiapan disimpan.",
        )
    )
        responsibilityDrafts.value[id] =
            state.value.teams.find((team) => team.id === id)
                ?.responsibilities ?? "";
}
const markReady = () =>
    mutate(
        "post",
        route("tenant.ttx.sessions.ready", state.value.session.id),
        {},
        "Session telah ditandai siap.",
    );
const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString("id-ID", {
              day: "numeric",
              month: "short",
              year: "numeric",
          })
        : "Belum dijadwalkan";
</script>

<template>
    <Head :title="`Persiapan - ${state.session.title}`" />
    <AppLayout title="Persiapan Tabletop">
        <div class="prepare-page fade-in">
            <Link
                :href="route('tenant.ttx.sessions.index')"
                class="back-link text-sm"
                >Kembali ke Sessions</Link
            >
            <header class="prepare-header">
                <p class="eyebrow">Persiapan Exercise</p>
                <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">
                    {{ state.session.title }}
                </h1>
                <p class="mt-2 text-sm t-muted">
                    {{ formatDate(state.session.scheduled_at) }} · Fasilitator
                    {{ state.facilitator?.name }}
                </p>
            </header>
            <p v-if="error" class="message message-error" role="alert">
                {{ error }}
            </p>
            <p
                v-if="notice"
                class="message message-success"
                role="status"
                aria-live="polite"
            >
                {{ notice }}
            </p>

            <section class="prepare-section" aria-labelledby="context-heading">
                <p class="section-number">01 / Exercise Context</p>
                <h2 id="context-heading" class="section-title">
                    {{ state.exercise_context?.title ?? state.exercise_title }}
                </h2>
                <p class="narrative">
                    {{
                        state.exercise_context?.scenario ||
                        "Deskripsi skenario belum tersedia."
                    }}
                </p>
                <dl class="facts">
                    <div>
                        <dt>Durasi / cakupan</dt>
                        <dd>
                            {{
                                state.exercise_context?.scope ||
                                "Tidak ditentukan"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Situation updates</dt>
                        <dd>{{ state.inject_count }}</dd>
                    </div>
                </dl>
            </section>

            <section
                class="prepare-section"
                aria-labelledby="capabilities-heading"
            >
                <p class="section-number">02 / Objectives & Capabilities</p>
                <h2 id="capabilities-heading" class="section-title">
                    Yang akan diuji
                </h2>
                <p
                    v-if="state.exercise_context?.objectives"
                    class="narrative whitespace-pre-wrap"
                >
                    {{ state.exercise_context.objectives }}
                </p>
                <div class="capability-list">
                    <article
                        v-for="capability in state.capabilities"
                        :key="capability.code"
                        class="capability-row"
                    >
                        <strong>{{ capability.code }}</strong>
                        <div>
                            <h3>{{ capability.label }}</h3>
                            <p>{{ capability.description }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="prepare-section" aria-labelledby="playbook-heading">
                <p class="section-number">03 / Playbook Baseline</p>
                <h2 id="playbook-heading" class="section-title">
                    {{ state.playbook?.title ?? "Playbook belum tersedia" }}
                </h2>
                <p class="narrative">{{ state.playbook?.description }}</p>
                <p class="reference-note">
                    Playbook adalah panduan respons organisasi yang menjadi
                    referensi exercise.
                </p>
                <ol
                    v-if="state.playbook?.structured_phases?.length"
                    class="phase-list"
                >
                    <li
                        v-for="(phase, index) in state.playbook
                            .structured_phases"
                        :key="phase.key"
                    >
                        <span>{{ String(index + 1).padStart(2, "0") }}</span>
                        <div>
                            <h3>{{ phase.title }}</h3>
                            <p>{{ phase.guidance }}</p>
                            <small>{{
                                phase.capability_codes.join(" · ")
                            }}</small>
                        </div>
                    </li>
                </ol>
                <p v-else class="legacy-playbook whitespace-pre-wrap">
                    {{
                        state.playbook?.content ||
                        "Isi Playbook legacy tidak tersedia."
                    }}
                </p>
            </section>

            <section class="prepare-section" aria-labelledby="teams-heading">
                <p class="section-number">04 / Teams & Participants</p>
                <h2 id="teams-heading" class="section-title">
                    Susunan exercise
                </h2>
                <div v-if="rosterByTeam.length" class="roster-list">
                    <article
                        v-for="team in rosterByTeam"
                        :key="team.id"
                        class="roster-row"
                    >
                        <div>
                            <h3>{{ team.name }}</h3>
                            <p>
                                {{ team.description || "Tanpa deskripsi tim" }}
                            </p>
                        </div>
                        <div class="roster-members">
                            <span
                                v-for="person in team.participants"
                                :key="person.assignment_id"
                                >{{ person.name
                                }}<button
                                    v-if="person.can_remove"
                                    type="button"
                                    :disabled="busy"
                                    @click="
                                        removeParticipant(person.assignment_id)
                                    "
                                >
                                    Hapus
                                </button></span
                            ><em v-if="!team.participants.length"
                                >Belum ada peserta</em
                            >
                        </div>
                        <button
                            v-if="team.can_remove"
                            type="button"
                            class="text-action"
                            :disabled="busy"
                            @click="removeTeam(team.id)"
                        >
                            Hapus tim
                        </button>
                    </article>
                </div>
                <EmptyState
                    v-else
                    title="Belum ada tim"
                    message="Buat minimal satu tim fungsional untuk exercise ini."
                />
                <div v-if="canEdit" class="management-grid">
                    <form class="compact-form" @submit.prevent="addTeam">
                        <h3>Tambah tim</h3>
                        <input
                            v-model="teamForm.name"
                            class="input"
                            maxlength="120"
                            required
                            aria-label="Nama tim"
                            placeholder="Nama tim"
                            :disabled="busy"
                        /><input
                            v-model="teamForm.description"
                            class="input"
                            maxlength="1000"
                            aria-label="Deskripsi tim opsional"
                            placeholder="Deskripsi opsional"
                            :disabled="busy"
                        /><button class="btn btn-secondary" :disabled="busy">
                            {{ busy ? "Memproses..." : "Tambah Tim" }}
                        </button>
                    </form>
                    <form
                        v-if="
                            state.assignable_users.length && state.teams.length
                        "
                        class="compact-form"
                        @submit.prevent="assignParticipant"
                    >
                        <h3>Tempatkan peserta</h3>
                        <select
                            v-model="participantForm.user_id"
                            aria-label="Peserta yang ditugaskan"
                            required
                            class="input"
                            :disabled="busy"
                        >
                            <option disabled value="">Pilih learner</option>
                            <option
                                v-for="user in state.assignable_users"
                                :key="user.id"
                                :value="user.id"
                            >
                                {{ user.name }}
                            </option></select
                        ><select
                            v-model="participantForm.team_id"
                            aria-label="Tim peserta"
                            required
                            class="input"
                            :disabled="busy"
                        >
                            <option disabled value="">Pilih tim</option>
                            <option
                                v-for="team in state.teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.name }}
                            </option></select
                        ><button class="btn btn-secondary" :disabled="busy">
                            {{ busy ? "Memproses..." : "Tambahkan Peserta" }}
                        </button>
                    </form>
                </div>
            </section>

            <section
                class="prepare-section"
                aria-labelledby="ownership-heading"
            >
                <p class="section-number">05 / Responsibility Ownership</p>
                <h2 id="ownership-heading" class="section-title">
                    Pemilik area respons
                </h2>
                <p class="narrative">
                    Tetapkan tepat satu Primary untuk setiap area relevan. Tim
                    Support bersifat opsional.
                </p>
                <div v-if="relevantPhases.length" class="ownership-table-wrap">
                    <table class="ownership-table">
                        <thead>
                            <tr>
                                <th>Response area</th>
                                <th>Primary</th>
                                <th>Support</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="phase in relevantPhases"
                                :key="phase.key"
                                :class="{
                                    uncovered:
                                        state.readiness.uncovered_relevant_phase_keys?.includes(
                                            phase.key,
                                        ),
                                }"
                            >
                                <th scope="row">
                                    <span>{{ phase.title }}</span
                                    ><small>{{
                                        phase.capability_codes.join(" · ")
                                    }}</small>
                                </th>
                                <td>
                                    <select
                                        :value="primaryTeamId(phase.key)"
                                        class="input"
                                        :disabled="!canEdit || busy"
                                        :aria-label="`Primary untuk ${phase.title}`"
                                        @change="
                                            setPrimary(
                                                phase.key,
                                                $event.target.value,
                                            )
                                        "
                                    >
                                        <option disabled value="">
                                            Belum ditetapkan
                                        </option>
                                        <option
                                            v-for="team in state.teams"
                                            :key="team.id"
                                            :value="team.id"
                                        >
                                            {{ team.name }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <div class="support-options">
                                        <label
                                            v-for="team in state.teams"
                                            :key="team.id"
                                            ><input
                                                type="checkbox"
                                                :checked="
                                                    isSupport(
                                                        phase.key,
                                                        team.id,
                                                    )
                                                "
                                                :disabled="
                                                    !canEdit ||
                                                    busy ||
                                                    primaryTeamId(phase.key) ===
                                                        team.id
                                                "
                                                @change="
                                                    toggleSupport(
                                                        phase.key,
                                                        team.id,
                                                        $event.target.checked,
                                                    )
                                                "
                                            /><span>{{
                                                team.name
                                            }}</span></label
                                        ><span
                                            v-if="!state.teams.length"
                                            class="t-muted"
                                            >Belum ada tim</span
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="message">
                    Session legacy menggunakan catatan persiapan tim sebagai
                    baseline readiness.
                </p>
            </section>

            <section class="prepare-section" aria-labelledby="notes-heading">
                <p class="section-number">06 / Optional Preparation Notes</p>
                <h2 id="notes-heading" class="section-title">
                    Catatan persiapan
                </h2>
                <p class="narrative">
                    Catatan khusus tim sebelum exercise dimulai. Pada session
                    V2, catatan ini opsional; ownership mengikuti assignment
                    fase. Pada session legacy, catatan persiapan tetap
                    diperlukan.
                </p>
                <div class="notes-grid">
                    <article v-for="team in state.teams" :key="team.id">
                        <label :for="`notes-${team.id}`">{{ team.name }}</label
                        ><textarea
                            :id="`notes-${team.id}`"
                            v-model="responsibilityDrafts[team.id]"
                            class="input"
                            rows="4"
                            maxlength="5000"
                            :disabled="!canEdit || busy"
                            placeholder="Konteks atau persiapan khusus tim (opsional)."
                        ></textarea
                        ><button
                            v-if="canEdit"
                            class="btn btn-secondary"
                            :disabled="busy"
                            @click="saveNotes(team.id)"
                        >
                            {{ busy ? "Menyimpan..." : "Simpan Catatan" }}
                        </button>
                    </article>
                </div>
            </section>

            <section
                class="prepare-section"
                aria-labelledby="readiness-heading"
            >
                <p class="section-number">07 / Readiness</p>
                <h2 id="readiness-heading" class="section-title">
                    Pemeriksaan operasional
                </h2>
                <ul class="check-list">
                    <li
                        v-for="check in readinessChecks"
                        :key="check.label"
                        :class="check.ok ? 'check-ok' : 'check-missing'"
                    >
                        <span aria-hidden="true">{{
                            check.ok ? "✓" : "!"
                        }}</span
                        >{{ check.label }}
                    </li>
                </ul>
                <div
                    v-if="state.readiness.uncovered_relevant_phase_keys?.length"
                    class="ownership-warning"
                    role="alert"
                >
                    <strong>Primary owner masih diperlukan untuk:</strong>
                    <ul>
                        <li
                            v-for="key in state.readiness
                                .uncovered_relevant_phase_keys"
                            :key="key"
                        >
                            {{ phaseByKey[key]?.title ?? key }}
                        </li>
                    </ul>
                </div>
            </section>

            <section class="start-section">
                <div>
                    <p class="section-number">08 / Start</p>
                    <h2 class="section-title">
                        {{
                            state.session.status === "draft"
                                ? "Tandai siap saat semua pemeriksaan terpenuhi"
                                : "Exercise siap dibuka di Control Room"
                        }}
                    </h2>
                </div>
                <button
                    v-if="state.session.status === 'draft'"
                    class="btn btn-primary"
                    :disabled="busy || !state.permissions.can_mark_ready"
                    @click="markReady"
                >
                    {{ busy ? "Memproses..." : "Tandai Siap" }}</button
                ><Link
                    v-else-if="state.can_open_console"
                    :href="
                        route('tenant.ttx.sessions.console', state.session.id)
                    "
                    class="btn btn-primary"
                    >Buka Control Room</Link
                >
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.prepare-page {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.prepare-header {
    padding: var(--sp-6) 0 var(--sp-8);
    max-width: 52rem;
}
.eyebrow,
.section-number {
    color: var(--brand);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.prepare-section {
    padding: var(--sp-8) 0;
    border-top: 1px solid var(--line);
}
.section-title {
    margin-top: var(--sp-2);
    color: var(--ink);
    font-family: var(--font-sans);
    font-size: 1.35rem;
    font-weight: 700;
}
.narrative {
    max-width: 48rem;
    margin-top: var(--sp-3);
    color: var(--muted);
    line-height: 1.7;
    overflow-wrap: anywhere;
}
.facts {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--sp-5);
    max-width: 48rem;
    margin-top: var(--sp-5);
}
.facts dt {
    color: var(--muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.facts dd {
    margin-top: var(--sp-1);
    color: var(--ink);
}
.message {
    margin: var(--sp-4) 0;
    padding: var(--sp-3) var(--sp-4);
    border-left: 3px solid var(--line);
    background: var(--surface-2);
    color: var(--muted);
}
.message-error {
    border-color: var(--danger);
    color: var(--danger);
}
.message-success {
    border-color: var(--ok);
    color: var(--ok);
}
.capability-list,
.phase-list,
.roster-list {
    max-width: 64rem;
    margin-top: var(--sp-5);
    border-top: 1px solid var(--line);
}
.capability-row {
    display: grid;
    grid-template-columns: 5rem minmax(0, 1fr);
    gap: var(--sp-4);
    padding: var(--sp-4) 0;
    border-bottom: 1px solid var(--line);
}
.capability-row strong {
    color: var(--brand);
}
.capability-row h3,
.phase-list h3,
.roster-row h3,
.compact-form h3 {
    color: var(--ink);
    font-weight: 700;
}
.capability-row p,
.phase-list p,
.roster-row p {
    margin-top: 0.25rem;
    color: var(--muted);
    line-height: 1.6;
}
.reference-note {
    margin-top: var(--sp-3);
    color: var(--muted);
    font-size: 0.8125rem;
}
.phase-list {
    list-style: none;
    padding: 0;
}
.phase-list li {
    display: grid;
    grid-template-columns: 3rem minmax(0, 1fr);
    gap: var(--sp-4);
    padding: var(--sp-4) 0;
    border-bottom: 1px solid var(--line);
}
.phase-list > li > span {
    color: var(--muted);
    font-variant-numeric: tabular-nums;
}
.phase-list small {
    display: block;
    margin-top: var(--sp-2);
    color: var(--brand);
}
.legacy-playbook {
    max-width: 48rem;
    margin-top: var(--sp-5);
    color: var(--ink);
    line-height: 1.7;
}
.roster-row {
    display: grid;
    grid-template-columns: minmax(11rem, 1fr) minmax(12rem, 1.5fr) auto;
    gap: var(--sp-5);
    align-items: start;
    padding: var(--sp-4) 0;
    border-bottom: 1px solid var(--line);
}
.roster-members {
    display: flex;
    flex-direction: column;
    gap: var(--sp-2);
    color: var(--ink);
}
.roster-members span {
    display: flex;
    justify-content: space-between;
    gap: var(--sp-3);
}
.roster-members button,
.text-action {
    color: var(--brand);
    font-size: 0.8125rem;
}
.roster-members button:hover,
.text-action:hover {
    text-decoration: underline;
}
.management-grid,
.notes-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--sp-6);
    margin-top: var(--sp-6);
}
.compact-form {
    display: grid;
    gap: var(--sp-3);
    padding-left: var(--sp-4);
    border-left: 2px solid var(--line);
}
.compact-form .btn {
    justify-self: start;
}
.ownership-table-wrap {
    margin-top: var(--sp-5);
    overflow-x: auto;
}
.ownership-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
}
.ownership-table th,
.ownership-table td {
    padding: var(--sp-4);
    border-bottom: 1px solid var(--line);
    text-align: left;
    vertical-align: top;
}
.ownership-table thead th {
    color: var(--muted);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.ownership-table tbody th {
    color: var(--ink);
}
.ownership-table tbody th span,
.ownership-table tbody th small {
    display: block;
}
.ownership-table tbody th small {
    margin-top: 0.3rem;
    color: var(--muted);
    font-weight: 400;
}
.ownership-table tr.uncovered {
    background: color-mix(in srgb, var(--warn) 8%, transparent);
}
.support-options {
    display: flex;
    flex-wrap: wrap;
    gap: var(--sp-2) var(--sp-4);
}
.support-options label {
    display: inline-flex;
    align-items: center;
    gap: var(--sp-2);
    color: var(--ink);
    font-size: 0.875rem;
}
.notes-grid article {
    display: grid;
    gap: var(--sp-2);
}
.notes-grid label {
    color: var(--ink);
    font-weight: 700;
}
.notes-grid textarea {
    width: 100%;
    resize: vertical;
}
.notes-grid .btn {
    justify-self: start;
}
.check-list {
    display: grid;
    gap: var(--sp-3);
    margin-top: var(--sp-5);
    padding: 0;
    list-style: none;
}
.check-list li {
    display: flex;
    gap: var(--sp-3);
    color: var(--ink);
}
.check-list span {
    display: inline-grid;
    width: 1.4rem;
    height: 1.4rem;
    place-items: center;
    border: 1px solid currentColor;
    border-radius: 50%;
    font-weight: 800;
}
.check-ok span {
    color: var(--ok);
}
.check-missing span {
    color: var(--warn);
}
.ownership-warning {
    max-width: 42rem;
    margin-top: var(--sp-5);
    padding: var(--sp-4);
    border-left: 3px solid var(--warn);
    background: var(--surface-2);
    color: var(--ink);
}
.ownership-warning ul {
    margin: var(--sp-2) 0 0;
    padding-left: 1.25rem;
}
.start-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-5);
    padding: var(--sp-8) 0;
    border-top: 1px solid var(--line);
}
button:focus-visible,
select:focus-visible,
input:focus-visible,
textarea:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}
@media (max-width: 768px) {
    .facts,
    .management-grid,
    .notes-grid {
        grid-template-columns: 1fr;
    }
    .roster-row {
        grid-template-columns: 1fr;
    }
    .start-section {
        align-items: stretch;
        flex-direction: column;
    }
    .start-section .btn {
        width: 100%;
    }
    .prepare-section {
        padding: var(--sp-6) 0;
    }
}
@media (max-width: 420px) {
    .capability-row,
    .phase-list li {
        grid-template-columns: 1fr;
        gap: var(--sp-2);
    }
    .prepare-header {
        padding-top: var(--sp-4);
    }
}
.prepare-page {
    overflow-wrap: anywhere;
}
.prepare-page .input {
    border-radius: var(--r-lg);
    min-width: 0;
}
.prepare-page .input:focus {
    box-shadow: none;
}
.prepare-page .btn:hover {
    box-shadow: none;
}
.prepare-page button:not(.btn),
.prepare-page select,
.prepare-page summary {
    transition:
        background 160ms ease,
        color 160ms ease,
        border-color 160ms ease;
}
.prepare-page button:not(.btn):active {
    transform: translateY(1px);
}
.prepare-page input[type="checkbox"] {
    accent-color: var(--brand);
}
@media (max-width: 768px) {
    .ownership-table {
        min-width: 0;
    }
    .ownership-table thead {
        display: none;
    }
    .ownership-table tbody,
    .ownership-table tr,
    .ownership-table th,
    .ownership-table td {
        display: block;
        width: 100%;
    }
    .ownership-table tr {
        border-bottom: 1px solid var(--line);
        padding: var(--sp-3) 0;
    }
    .ownership-table th,
    .ownership-table td {
        border: 0;
        padding: var(--sp-2) 0;
    }
    .ownership-table td:nth-child(2)::before {
        content: "Primary";
        display: block;
        margin-bottom: var(--sp-1);
        color: var(--muted);
        font-size: 0.75rem;
    }
    .ownership-table td:nth-child(3)::before {
        content: "Support";
        display: block;
        margin-bottom: var(--sp-1);
        color: var(--muted);
        font-size: 0.75rem;
    }
    .roster-row {
        min-width: 0;
    }
}
</style>
