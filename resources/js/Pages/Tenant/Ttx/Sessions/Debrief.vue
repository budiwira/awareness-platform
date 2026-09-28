<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTextarea from '@/Components/BaseTextarea.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    sessionId: { type: [String, Number], required: true },
    resultMode: { type: Boolean, default: false },
});

const data = ref(null);
const loading = ref(true);
const error = ref('');
const savingEvaluation = ref(false);
const savingAar = ref(false);
const savingAction = ref(null);
const finalizing = ref(false);
const notice = ref('');
const evaluation = ref([]);
const actionItems = ref([]);
const aar = reactive({ overall_summary: '', strengths: '', improvement_areas: '', key_lessons: '' });
const emptyAction = () => ({ title: '', owner: '', priority: 'medium', due_date: '', status: 'open', capability_code: '', playbook_phase_key: '', category: '' });
const newAction = reactive(emptyAction());

const editable = computed(() => !props.resultMode && data.value?.editable === true);
const complete = computed(() => data.value?.session?.status === 'completed');
const ratingOptions = computed(() => data.value?.rating_options ?? []);
const phases = computed(() => data.value?.session?.playbook?.structured_phases ?? []);
const requiredCount = computed(() => evaluation.value.filter((item) => item.required).length);
const capabilityOptions = computed(() => evaluation.value.filter((item) => data.value?.session?.response_contract_version < 2 || data.value?.session?.scenario_snapshot?.capability_codes?.includes(item.code)));
const categoryOptions = [ { value: 'corrective_action', label: 'Perbaikan Operasional / Proses / Tim' }, { value: 'playbook_improvement', label: 'Perbaikan Playbook' } ];
const aarFields = [ { key: 'overall_summary', label: 'Ringkasan keseluruhan' }, { key: 'strengths', label: 'Kekuatan' }, { key: 'improvement_areas', label: 'Area perbaikan' }, { key: 'key_lessons', label: 'Pelajaran utama' } ];
const phaseTitle = (key) => phases.value.find((phase) => phase.key === key)?.title ?? key;
const categoryLabel = (value) => categoryOptions.find((option) => option.value === value)?.label ?? 'Tidak ditautkan';
const ratingLabel = (value) => ratingOptions.value.find((option) => option.value === value)?.label ?? 'Belum dinilai';
const normalizeEvaluation = (items) => items.map((item) => ({ ...item, rating: item.rating ?? '', evidence: item.evidence ?? '', finding: item.finding ?? '' }));
const normalizeAction = (item) => ({ ...item, due_date: item.due_date?.slice(0, 10) ?? '', capability_code: item.capability_code ?? '', playbook_phase_key: item.playbook_phase_key ?? '', category: item.category ?? '' });
const actionPayload = (item) => ({ title: item.title, owner: item.owner, priority: item.priority, due_date: item.due_date || null, status: item.status, capability_code: item.capability_code || null, playbook_phase_key: item.playbook_phase_key || null, category: item.category || null });
const unsaved = computed(() => data.value && (
    JSON.stringify(evaluation.value) !== JSON.stringify(normalizeEvaluation(data.value.dimensions))
    || JSON.stringify(actionItems.value.map(actionPayload)) !== JSON.stringify(data.value.action_items.map((item) => actionPayload(normalizeAction(item))))
    || aarFields.some(({ key }) => aar[key] !== (data.value.after_action_summary?.[key] ?? ''))
    || JSON.stringify(newAction) !== JSON.stringify(emptyAction())
));
const busy = computed(() => savingEvaluation.value || savingAar.value || savingAction.value !== null || finalizing.value);

const hydrate = (payload) => {
    data.value = payload;
    evaluation.value = normalizeEvaluation(payload.dimensions);
    actionItems.value = payload.action_items.map(normalizeAction);
    Object.assign(aar, payload.after_action_summary ?? {
        overall_summary: '', strengths: '', improvement_areas: '', key_lessons: '',
    });
};

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get(route('tenant.ttx.sessions.debrief', props.sessionId), {
            headers: { Accept: 'application/json' },
        });
        hydrate(response.data);
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Data debrief tidak dapat dimuat.';
    } finally {
        loading.value = false;
    }
};

const requestError = (err, fallback) => {
    const errors = err.response?.data?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.response?.data?.message ?? fallback;
};

const saveEvaluation = async () => {
    savingEvaluation.value = true;
    error.value = '';
    try {
        const response = await axios.put(route('tenant.ttx.sessions.evaluation.update', props.sessionId), {
            evaluations: evaluation.value.filter((item) => item.rating || item.evidence.trim() || item.finding.trim()).map(({ dimension, rating, evidence, finding }) => ({
                dimension, rating, evidence: evidence || null, finding: finding || null,
            })),
        });
        data.value.dimensions = response.data.dimensions;
        evaluation.value = normalizeEvaluation(response.data.dimensions);
        notice.value = 'Evaluasi kualitatif disimpan.';
    } catch (err) {
        error.value = requestError(err, 'Evaluasi tidak dapat disimpan.');
    } finally {
        savingEvaluation.value = false;
    }
};

const saveAar = async () => {
    savingAar.value = true;
    error.value = '';
    try {
        const response = await axios.put(route('tenant.ttx.sessions.aar.update', props.sessionId), { ...aar });
        data.value.after_action_summary = response.data.after_action_summary;
        Object.assign(aar, response.data.after_action_summary);
        notice.value = 'After-Action Summary disimpan.';
    } catch (err) {
        error.value = requestError(err, 'After-Action Summary tidak dapat disimpan.');
    } finally {
        savingAar.value = false;
    }
};

const addAction = async () => {
    savingAction.value = 'new';
    error.value = '';
    try {
        const response = await axios.post(route('tenant.ttx.sessions.action-items.store', props.sessionId), actionPayload(newAction));
        data.value.action_items.push(response.data);
        actionItems.value.push(normalizeAction(response.data));
        Object.assign(newAction, emptyAction());
        notice.value = 'Action item ditambahkan.';
    } catch (err) {
        error.value = requestError(err, 'Action item tidak dapat ditambahkan.');
    } finally {
        savingAction.value = null;
    }
};

const saveAction = async (item) => {
    savingAction.value = item.id;
    error.value = '';
    try {
        const response = await axios.put(route('tenant.ttx.sessions.action-items.update', [props.sessionId, item.id]), actionPayload(item));
        data.value.action_items = data.value.action_items.map((entry) => entry.id === item.id ? response.data : entry);
        actionItems.value = actionItems.value.map((entry) => entry.id === item.id ? normalizeAction(response.data) : entry);
        notice.value = 'Action item diperbarui.';
    } catch (err) {
        error.value = requestError(err, 'Action item tidak dapat diperbarui.');
    } finally {
        savingAction.value = null;
    }
};

const finalize = async () => {
    if (unsaved.value || busy.value) {
        error.value = 'Simpan seluruh perubahan atau kosongkan draf action item baru sebelum finalisasi.';
        return;
    }
    if (!window.confirm('Selesaikan sesi? Debrief tidak dapat diubah atau dibuka kembali.')) return;
    finalizing.value = true;
    error.value = '';
    try {
        const response = await axios.post(route('tenant.ttx.sessions.complete', props.sessionId));
        hydrate(response.data);
        router.visit(route('tenant.ttx.sessions.result', props.sessionId));
    } catch (err) {
        error.value = requestError(err, 'Sesi belum dapat diselesaikan.');
    } finally {
        finalizing.value = false;
    }
};

const formatDateTime = (value) => value
    ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(value))
    : '-';

onMounted(load);
</script>

<template>
    <Head :title="complete ? 'Hasil Tabletop Exercise' : 'Debrief Tabletop Exercise'" />
    <AppLayout :title="complete ? 'Hasil Tabletop Exercise' : 'Debrief Tabletop Exercise'">
        <div v-if="loading" class="space-y-6" aria-label="Memuat debrief">
            <div class="skeleton h-36 rounded-2xl"></div>
            <div class="skeleton h-96 rounded-2xl"></div>
        </div>

        <BaseAlert v-else-if="error && !data" variant="danger" title="Gagal Memuat">
            {{ error }}
            <BaseButton class="mt-3" size="sm" variant="secondary" @click="load">Coba Lagi</BaseButton>
        </BaseAlert>

        <div v-else-if="data" class="debrief-page fade-in">
            <header class="hero-card">
                <div>
                    <p class="eyebrow">{{ complete ? 'Hasil Final' : 'Debrief & Evaluasi' }}</p>
                    <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">{{ data.session.title }}</h1>
                    <div class="hero-meta">
                        <BaseBadge :variant="complete ? 'success' : 'brand'">{{ complete ? 'Selesai' : 'Debrief' }}</BaseBadge>
                        <span class="t-muted text-sm">{{ data.session.injects.length }} injeksi dirilis</span>
                        <span v-if="data.session.duration_minutes !== null" class="t-muted text-sm">Durasi {{ data.session.duration_minutes }} menit</span>
                    </div>
                </div>
                <Link :href="route('tenant.ttx.sessions.index')" class="back-link">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Kembali ke Sessions
                </Link>
            </header>

            <BaseAlert v-if="complete" variant="success" title="Debrief telah final">
                Sesi selesai dan seluruh evaluasi, action item, serta ringkasan kini baca-saja.
            </BaseAlert>
            <BaseAlert v-if="error" variant="danger" title="Tindakan Gagal">{{ error }}</BaseAlert>
            <BaseAlert v-if="notice" variant="success" title="Berhasil">{{ notice }}</BaseAlert>

            <section class="section-card">
                <div class="section-heading"><div><p class="eyebrow">Konteks Exercise</p><h2 class="font-display text-xl font-bold t-ink">{{ data.session.scenario }}</h2></div></div>
                <p class="situation">{{ data.session.scenario_snapshot?.scenario }}</p>
                <div class="response-box mt-4">
                    <span>PLAYBOOK REFERENCE</span>
                    <h3 class="mt-1 font-semibold t-ink">{{ data.session.playbook?.title }}</h3>
                    <p>{{ data.session.playbook?.description }}</p>
                    <details class="mt-3"><summary class="cursor-pointer rounded-lg p-2 font-medium t-ink transition hover:bg-[var(--surface-2)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand)] active:translate-y-px">Lihat fase panduan</summary>
                        <div v-if="phases.length" class="timeline-list">
                            <article v-for="phase in phases" :key="phase.key">
                                <h4 class="font-semibold t-ink">{{ phase.title }}</h4>
                                <p>{{ phase.guidance }}</p>
                                <p class="t-muted">Capability: {{ phase.capability_codes.join(', ') }}</p>
                            </article>
                        </div>
                        <p v-else class="mt-2 whitespace-pre-wrap">{{ data.session.playbook?.content ?? 'Referensi Playbook historis tidak tersedia.' }}</p>
                    </details>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading"><div><p class="eyebrow">Roster Session</p><h2 class="font-display text-xl font-bold t-ink">Teams & Participants</h2></div></div>
                <div class="evaluation-grid">
                    <article v-for="team in data.session.teams" :key="team.id" class="evaluation-card">
                        <h3 class="font-semibold t-ink">{{ team.name }}</h3>
                        <p class="mt-2 whitespace-pre-wrap text-sm t-ink">{{ team.responsibilities }}</p>
                        <ul v-if="team.responsibility_assignments?.length" class="space-y-2 text-sm t-ink">
                            <li v-for="assignment in team.responsibility_assignments" :key="assignment.id">
                                <BaseBadge :variant="assignment.role === 'primary' ? 'brand' : 'neutral'">{{ assignment.role === 'primary' ? 'Primary' : 'Support' }}</BaseBadge>
                                {{ phaseTitle(assignment.playbook_phase_key) }}
                            </li>
                        </ul>
                        <p v-if="!team.participants.length" class="mt-2 text-sm t-muted">Tidak ada peserta.</p>
                        <ul v-else class="mt-2 space-y-1 text-sm t-muted"><li v-for="participant in team.participants" :key="participant.id">{{ participant.name }}</li></ul>
                    </article>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading">
                    <div><p class="eyebrow">Timeline Resmi</p><h2 class="font-display text-xl font-bold t-ink">Respons Selama Exercise</h2></div>
                </div>
                <div class="timeline-list">
                    <article v-for="inject in data.session.injects" :key="inject.id" class="timeline-item">
                        <div class="timeline-title">
                            <BaseBadge variant="neutral">Injeksi {{ inject.order }}</BaseBadge>
                            <strong>{{ inject.snapshot?.title ?? `Injeksi ${inject.order}` }}</strong>
                        </div>
                        <p class="situation">{{ inject.snapshot?.situation ?? inject.snapshot?.description }}</p>
                        <div class="team-result-list">
                            <div v-for="entry in inject.team_responses" :key="entry.team.id" class="response-box">
                                <div class="team-result-head">
                                    <strong>{{ entry.team.name }}</strong>
                                    <span class="date">{{ formatDateTime(entry.response?.submitted_at) }}</span>
                                </div>
                                <span>ACTUAL TEAM RESPONSE</span>
                                <p>{{ entry.response?.decision ?? 'Tidak ada respons.' }}</p>
                                <p v-if="entry.response?.rationale"><strong>Alasan keputusan:</strong> {{ entry.response.rationale }}</p>
                                <p v-if="entry.response?.immediate_actions"><strong>Tindakan segera:</strong> {{ entry.response.immediate_actions }}</p>
                                <p v-if="entry.response?.coordination_handoff"><strong>Koordinasi / handoff:</strong> {{ entry.response.coordination_handoff }}</p>
                                <p v-if="entry.response?.owner"><strong>Pemilik:</strong> {{ entry.response.owner }}</p>
                                <p v-if="entry.response?.escalation"><strong>Eskalasi:</strong> {{ entry.response.escalation }}</p>
                                <p v-if="entry.response?.unknowns"><strong>Informasi belum diketahui:</strong> {{ entry.response.unknowns }}</p>
                                <p v-if="entry.response?.notes"><strong>Catatan:</strong> {{ entry.response.notes }}</p>
                            </div>
                            <div v-if="inject.legacy_response" class="response-box">
                                <strong>Respons historis bersama</strong>
                                <p>{{ inject.legacy_response.decision }}</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading">
                    <div><p class="eyebrow">Capability Review</p><h2 class="font-display text-xl font-bold t-ink">Evaluasi Kualitatif</h2><p class="t-muted">{{ requiredCount }} dimensi wajib sesuai snapshot sesi. Evaluasi lain tetap ditampilkan sebagai konteks historis.</p></div>
                    <BaseButton v-if="editable" :loading="savingEvaluation" :disabled="savingEvaluation" @click="saveEvaluation">
                        {{ savingEvaluation ? 'Menyimpan...' : 'Simpan Evaluasi' }}
                    </BaseButton>
                </div>
                <div class="evaluation-grid">
                    <article v-for="item in evaluation" :key="item.dimension" class="evaluation-card">
                        <div class="dimension-title"><BaseBadge variant="brand">{{ item.code }}</BaseBadge><h3>{{ item.label }}</h3></div>
                        <BaseBadge :variant="item.required ? 'brand' : 'neutral'">{{ item.required ? 'Wajib / relevan' : 'Di luar cakupan wajib' }}</BaseBadge>
                        <template v-if="editable">
                        <BaseTextarea v-model="item.evidence" label="Evidence — fakta teramati" :rows="3" :maxlength="5000" />
                        <p class="field-help">Catat fakta objektif yang terlihat dalam keputusan, tindakan, atau koordinasi tim.</p>
                        <BaseTextarea v-model="item.finding" label="Finding — makna bukti" :rows="3" :maxlength="5000" />
                        <p class="field-help">Jelaskan makna fakta tersebut bagi efektivitas respons organisasi.</p>
                        <BaseSelect v-model="item.rating" label="Rating kualitatif" :required="item.required">
                            <option value="" disabled>Pilih rating</option>
                            <option v-for="option in ratingOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </BaseSelect>
                        </template>
                        <dl v-else class="report-fields">
                            <dt>Evidence — fakta teramati</dt><dd>{{ item.evidence || 'Tidak dicatat.' }}</dd>
                            <dt>Finding — makna bukti</dt><dd>{{ item.finding || 'Tidak dicatat (data historis).' }}</dd>
                            <dt>Rating kualitatif</dt><dd>{{ ratingLabel(item.rating) }}</dd>
                        </dl>
                    </article>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading"><div><p class="eyebrow">Tindak Lanjut</p><h2 class="font-display text-xl font-bold t-ink">Action Items</h2></div></div>
                <div v-if="actionItems.length" class="action-list">
                    <article v-for="item in actionItems" :key="item.id" class="action-card">
                        <template v-if="editable">
                        <BaseInput v-model="item.title" label="Action" :disabled="!editable" required />
                        <BaseInput v-model="item.owner" label="Pemilik" :disabled="!editable" required />
                        <BaseSelect v-model="item.priority" label="Prioritas" :disabled="!editable"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></BaseSelect>
                        <BaseInput v-model="item.due_date" label="Tenggat (opsional)" type="date" :disabled="!editable" />
                        <BaseSelect v-model="item.status" label="Status" :disabled="!editable"><option value="open">Terbuka</option><option value="completed">Selesai</option></BaseSelect>
                        <BaseSelect v-model="item.category" label="Kategori (opsional)"><option value="">Tidak ditautkan</option><option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option></BaseSelect>
                        <BaseSelect v-model="item.capability_code" label="Capability (opsional)"><option value="">Tidak ditautkan</option><option v-if="item.capability_code && !capabilityOptions.some((option) => option.code === item.capability_code)" :value="item.capability_code" disabled>{{ item.capability_code }} — historis, pilih tautan yang relevan</option><option v-for="option in capabilityOptions" :key="option.code" :value="option.code">{{ option.code }} — {{ option.label }}</option></BaseSelect>
                        <BaseSelect v-model="item.playbook_phase_key" label="Fase Playbook (opsional)"><option value="">Tidak ditautkan</option><option v-for="phase in phases" :key="phase.key" :value="phase.key">{{ phase.title }}</option></BaseSelect>
                        <BaseButton v-if="editable" variant="secondary" :loading="savingAction === item.id" :disabled="savingAction !== null" @click="saveAction(item)">Simpan</BaseButton>
                        </template>
                        <dl v-else class="report-fields action-report">
                            <dt>Action</dt><dd>{{ item.title }}</dd><dt>Pemilik</dt><dd>{{ item.owner }}</dd>
                            <dt>Kategori</dt><dd>{{ categoryLabel(item.category) }}</dd><dt>Capability</dt><dd>{{ item.capability_code || 'Tidak ditautkan' }}</dd>
                            <dt>Fase Playbook</dt><dd>{{ item.playbook_phase_key ? phaseTitle(item.playbook_phase_key) : 'Tidak ditautkan' }}</dd>
                            <dt>Prioritas / Status</dt><dd>{{ { low: 'Rendah', medium: 'Sedang', high: 'Tinggi' }[item.priority] }} / {{ item.status === 'completed' ? 'Selesai' : 'Terbuka' }}</dd>
                            <dt>Tenggat</dt><dd>{{ item.due_date ? formatDateTime(`${item.due_date}T00:00:00`) : 'Tidak ditentukan' }}</dd>
                        </dl>
                    </article>
                </div>
                <EmptyState v-else title="Belum ada action item" message="Tambahkan tindak lanjut hanya jika memang diperlukan." />
                <form v-if="editable" class="new-action" @submit.prevent="addAction">
                    <h3 class="font-display font-bold t-ink">Tambah Action Item</h3>
                    <div class="action-card">
                        <BaseInput v-model="newAction.title" label="Action" required />
                        <BaseInput v-model="newAction.owner" label="Pemilik" required />
                        <BaseSelect v-model="newAction.priority" label="Prioritas"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></BaseSelect>
                        <BaseInput v-model="newAction.due_date" label="Tenggat (opsional)" type="date" />
                        <BaseSelect v-model="newAction.status" label="Status"><option value="open">Terbuka</option><option value="completed">Selesai</option></BaseSelect>
                        <BaseSelect v-model="newAction.category" label="Kategori (opsional)"><option value="">Tidak ditautkan</option><option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option></BaseSelect>
                        <BaseSelect v-model="newAction.capability_code" label="Capability (opsional)"><option value="">Tidak ditautkan</option><option v-for="option in capabilityOptions" :key="option.code" :value="option.code">{{ option.code }} — {{ option.label }}</option></BaseSelect>
                        <BaseSelect v-model="newAction.playbook_phase_key" label="Fase Playbook (opsional)"><option value="">Tidak ditautkan</option><option v-for="phase in phases" :key="phase.key" :value="phase.key">{{ phase.title }}</option></BaseSelect>
                        <BaseButton type="submit" :loading="savingAction === 'new'" :disabled="savingAction !== null">{{ savingAction === 'new' ? 'Menambahkan...' : 'Tambah' }}</BaseButton>
                    </div>
                </form>
            </section>

            <section class="section-card">
                <div class="section-heading">
                    <div><p class="eyebrow">Dokumentasi Akhir</p><h2 class="font-display text-xl font-bold t-ink">After-Action Summary</h2></div>
                    <BaseButton v-if="editable" :loading="savingAar" :disabled="savingAar" @click="saveAar">{{ savingAar ? 'Menyimpan...' : 'Simpan Ringkasan' }}</BaseButton>
                </div>
                <div class="aar-grid">
                    <template v-for="field in aarFields" :key="field.key">
                        <BaseTextarea v-if="editable" v-model="aar[field.key]" :label="field.label" :rows="5" :maxlength="10000" required />
                        <div v-else class="report-fields"><h3>{{ field.label }}</h3><p>{{ aar[field.key] || 'Tidak dicatat.' }}</p></div>
                    </template>
                </div>
                <p v-if="editable" class="field-help">Keempat bagian ringkasan ini akan terlihat oleh peserta. Jangan sertakan bukti evaluasi atau catatan internal yang dilindungi.</p>
            </section>

            <section v-if="editable" class="finalize-card">
                <div><h2 class="font-display text-lg font-bold t-ink">Finalisasi Sesi</h2><p>Pastikan {{ requiredCount }} rating wajib{{ data.session.response_contract_version >= 2 ? ', Evidence, dan Finding yang bermakna' : '' }} serta seluruh ringkasan telah disimpan. Action Item opsional. Tindakan ini tidak dapat dibatalkan.</p><p v-if="unsaved" role="status">Masih ada perubahan belum disimpan.</p></div>
                <BaseButton variant="danger" :loading="finalizing" :disabled="busy || unsaved" @click="finalize">{{ finalizing ? 'Menyelesaikan...' : 'Selesaikan Sesi' }}</BaseButton>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.debrief-page { display: flex; flex-direction: column; gap: var(--sp-6); min-width: 0; }
.hero-card, .section-card, .finalize-card { padding: var(--sp-6); border: 1px solid var(--line); border-radius: var(--r-2xl); background: var(--surface); box-shadow: var(--shadow-sm); }
.hero-card, .section-heading, .finalize-card, .timeline-title, .dimension-title { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-4); }
.eyebrow { margin: 0 0 var(--sp-1); color: var(--muted); font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.hero-meta, .dimension-title { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sp-2); margin-top: var(--sp-3); }
.back-link { display: inline-flex; min-height: 44px; align-items: center; gap: var(--sp-2); padding: var(--sp-2) var(--sp-3); border-radius: var(--r-full); color: var(--muted); transition: color var(--dur-fast), background var(--dur-fast), transform var(--dur-fast); }
.back-link:hover { color: var(--ink); background: var(--surface-2); }.back-link:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }.back-link:active { transform: translateY(1px); }
.timeline-list, .action-list, .aar-grid { display: grid; gap: var(--sp-4); margin-top: var(--sp-5); }
.timeline-item, .evaluation-card, .action-card { padding: var(--sp-4); border: 1px solid var(--line); border-radius: var(--r-xl); background: var(--surface-2); }
.timeline-title { justify-content: flex-start; flex-wrap: wrap; }.timeline-title .date { margin-left: auto; color: var(--muted); font-size: .8rem; }.situation { color: var(--muted); white-space: pre-wrap; }
.team-result-list { display: grid; gap: var(--sp-3); margin-top: var(--sp-3); }
.team-result-head { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-3); margin-bottom: var(--sp-2); }
.response-box { padding: var(--sp-3); border-left: 3px solid var(--brand); background: var(--surface); }.response-box span { color: var(--muted); font-size: .75rem; font-weight: 700; text-transform: uppercase; }.response-box p { margin: var(--sp-1) 0 0; white-space: pre-wrap; }
.evaluation-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sp-4); margin-top: var(--sp-5); }.evaluation-card { display: grid; gap: var(--sp-4); }.dimension-title { justify-content: flex-start; margin: 0; }.dimension-title h3 { margin: 0; color: var(--ink); }
.action-card { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--sp-3); align-items: end; }.new-action { margin-top: var(--sp-6); padding-top: var(--sp-5); border-top: 1px solid var(--line); }.new-action h3 { margin-bottom: var(--sp-3); }
.field-help { color: var(--muted); font-size: .85rem; margin: 0; }.report-fields { min-width: 0; color: var(--ink); overflow-wrap: anywhere; }.report-fields dt, .report-fields h3 { font-weight: 700; margin-top: var(--sp-3); }.report-fields dd, .report-fields p { white-space: pre-wrap; margin: var(--sp-1) 0 0; }.action-report { grid-column: 1 / -1; }
.aar-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.finalize-card p { margin: var(--sp-1) 0 0; color: var(--muted); }.section-heading { align-items: flex-start; }
@media (max-width: 1024px) { .action-card { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .hero-card, .section-heading, .finalize-card { align-items: stretch; flex-direction: column; }.hero-card, .section-card, .finalize-card { padding: var(--sp-4); }.evaluation-grid, .aar-grid, .action-card { grid-template-columns: minmax(0, 1fr); }.timeline-title .date { width: 100%; margin-left: 0; } }
@media (prefers-reduced-motion: reduce) { .back-link { transition: none; } }
</style>
