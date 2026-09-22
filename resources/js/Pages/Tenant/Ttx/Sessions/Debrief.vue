<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseTextarea from '@/Components/BaseTextarea.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ sessionId: { type: [String, Number], required: true } });

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
const newAction = reactive({ title: '', owner: '', priority: 'medium', due_date: '', status: 'open' });

const editable = computed(() => data.value?.editable === true);
const complete = computed(() => data.value?.session?.status === 'completed');
const ratingOptions = computed(() => data.value?.rating_options ?? []);

const hydrate = (payload) => {
    data.value = payload;
    evaluation.value = payload.dimensions.map((item) => ({ ...item, rating: item.rating ?? '', evidence: item.evidence ?? '' }));
    actionItems.value = payload.action_items.map((item) => ({ ...item, due_date: item.due_date ?? '' }));
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
            evaluations: evaluation.value.map(({ dimension, rating, evidence }) => ({
                dimension, rating, evidence: evidence || null,
            })),
        });
        hydrate(response.data);
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
        hydrate(response.data);
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
        await axios.post(route('tenant.ttx.sessions.action-items.store', props.sessionId), {
            ...newAction, due_date: newAction.due_date || null,
        });
        Object.assign(newAction, { title: '', owner: '', priority: 'medium', due_date: '', status: 'open' });
        await load();
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
        await axios.put(route('tenant.ttx.sessions.action-items.update', [props.sessionId, item.id]), {
            title: item.title,
            owner: item.owner,
            priority: item.priority,
            due_date: item.due_date || null,
            status: item.status,
        });
        await load();
        notice.value = 'Action item diperbarui.';
    } catch (err) {
        error.value = requestError(err, 'Action item tidak dapat diperbarui.');
    } finally {
        savingAction.value = null;
    }
};

const finalize = async () => {
    if (!window.confirm('Selesaikan sesi? Debrief tidak dapat diubah atau dibuka kembali.')) return;
    finalizing.value = true;
    error.value = '';
    try {
        const response = await axios.post(route('tenant.ttx.sessions.complete', props.sessionId));
        hydrate(response.data);
        notice.value = 'Sesi selesai dan seluruh debrief telah dikunci.';
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
    <Head title="Debrief Tabletop Exercise" />
    <AppLayout title="Debrief Tabletop Exercise">
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
                    <p class="eyebrow">Debrief & Evaluasi</p>
                    <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">{{ data.session.title }}</h1>
                    <div class="hero-meta">
                        <BaseBadge :variant="complete ? 'success' : 'brand'">{{ complete ? 'Selesai' : 'Debrief' }}</BaseBadge>
                        <span class="t-muted text-sm">{{ data.session.injects.length }} injeksi dirilis</span>
                    </div>
                </div>
                <Link :href="route('tenant.ttx.sessions.console', props.sessionId)" class="back-link">
                    <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Konsol Fasilitator
                </Link>
            </header>

            <BaseAlert v-if="complete" variant="success" title="Debrief telah final">
                Sesi selesai dan seluruh evaluasi, action item, serta ringkasan kini baca-saja.
            </BaseAlert>
            <BaseAlert v-if="error" variant="danger" title="Tindakan Gagal">{{ error }}</BaseAlert>
            <BaseAlert v-if="notice" variant="success" title="Berhasil">{{ notice }}</BaseAlert>

            <section class="section-card">
                <div class="section-heading">
                    <div><p class="eyebrow">Timeline Resmi</p><h2 class="font-display text-xl font-bold t-ink">Respons Selama Exercise</h2></div>
                </div>
                <div class="timeline-list">
                    <article v-for="inject in data.session.injects" :key="inject.id" class="timeline-item">
                        <div class="timeline-title">
                            <BaseBadge variant="neutral">Injeksi {{ inject.order }}</BaseBadge>
                            <strong>{{ inject.snapshot?.title ?? `Injeksi ${inject.order}` }}</strong>
                            <span class="date">{{ formatDateTime(inject.response?.submitted_at) }}</span>
                        </div>
                        <p class="situation">{{ inject.snapshot?.situation ?? inject.snapshot?.description }}</p>
                        <div class="response-box"><span>Keputusan resmi</span><p>{{ inject.response?.decision ?? 'Tidak ada respons resmi.' }}</p></div>
                    </article>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading">
                    <div><p class="eyebrow">Enam Dimensi</p><h2 class="font-display text-xl font-bold t-ink">Evaluasi Kualitatif</h2></div>
                    <BaseButton v-if="editable" :loading="savingEvaluation" :disabled="savingEvaluation" @click="saveEvaluation">
                        {{ savingEvaluation ? 'Menyimpan...' : 'Simpan Evaluasi' }}
                    </BaseButton>
                </div>
                <div class="evaluation-grid">
                    <article v-for="item in evaluation" :key="item.dimension" class="evaluation-card">
                        <div class="dimension-title"><BaseBadge variant="brand">{{ item.code }}</BaseBadge><h3>{{ item.label }}</h3></div>
                        <BaseSelect v-model="item.rating" label="Rating" :disabled="!editable" required>
                            <option value="" disabled>Pilih rating</option>
                            <option v-for="option in ratingOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </BaseSelect>
                        <BaseTextarea v-model="item.evidence" label="Bukti / catatan fasilitator" :disabled="!editable" :rows="3" />
                    </article>
                </div>
            </section>

            <section class="section-card">
                <div class="section-heading"><div><p class="eyebrow">Tindak Lanjut</p><h2 class="font-display text-xl font-bold t-ink">Action Items</h2></div></div>
                <div v-if="actionItems.length" class="action-list">
                    <article v-for="item in actionItems" :key="item.id" class="action-card">
                        <BaseInput v-model="item.title" label="Action" :disabled="!editable" required />
                        <BaseInput v-model="item.owner" label="Pemilik" :disabled="!editable" required />
                        <BaseSelect v-model="item.priority" label="Prioritas" :disabled="!editable"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></BaseSelect>
                        <BaseInput v-model="item.due_date" label="Tenggat (opsional)" type="date" :disabled="!editable" />
                        <BaseSelect v-model="item.status" label="Status" :disabled="!editable"><option value="open">Terbuka</option><option value="completed">Selesai</option></BaseSelect>
                        <BaseButton v-if="editable" variant="secondary" :loading="savingAction === item.id" :disabled="savingAction !== null" @click="saveAction(item)">Simpan</BaseButton>
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
                    <BaseTextarea v-model="aar.overall_summary" label="Ringkasan keseluruhan" :disabled="!editable" :rows="5" required />
                    <BaseTextarea v-model="aar.strengths" label="Kekuatan" :disabled="!editable" :rows="5" required />
                    <BaseTextarea v-model="aar.improvement_areas" label="Area perbaikan" :disabled="!editable" :rows="5" required />
                    <BaseTextarea v-model="aar.key_lessons" label="Pelajaran utama" :disabled="!editable" :rows="5" required />
                </div>
            </section>

            <section v-if="editable" class="finalize-card">
                <div><h2 class="font-display text-lg font-bold t-ink">Finalisasi Sesi</h2><p>Pastikan enam rating dan seluruh bagian ringkasan telah disimpan. Tindakan ini tidak dapat dibatalkan.</p></div>
                <BaseButton variant="danger" :loading="finalizing" :disabled="finalizing" @click="finalize">{{ finalizing ? 'Menyelesaikan...' : 'Selesaikan Sesi' }}</BaseButton>
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
.response-box { margin-top: var(--sp-3); padding: var(--sp-3); border-left: 3px solid var(--brand); background: var(--surface); }.response-box span { color: var(--muted); font-size: .75rem; font-weight: 700; text-transform: uppercase; }.response-box p { margin: var(--sp-1) 0 0; white-space: pre-wrap; }
.evaluation-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sp-4); margin-top: var(--sp-5); }.evaluation-card { display: grid; gap: var(--sp-4); }.dimension-title { justify-content: flex-start; margin: 0; }.dimension-title h3 { margin: 0; color: var(--ink); }
.action-card { display: grid; grid-template-columns: minmax(12rem, 2fr) minmax(10rem, 1fr) 9rem 11rem 9rem auto; gap: var(--sp-3); align-items: end; }.new-action { margin-top: var(--sp-6); padding-top: var(--sp-5); border-top: 1px solid var(--line); }.new-action h3 { margin-bottom: var(--sp-3); }
.aar-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.finalize-card p { margin: var(--sp-1) 0 0; color: var(--muted); }.section-heading { align-items: flex-start; }
@media (max-width: 1024px) { .action-card { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .hero-card, .section-heading, .finalize-card { align-items: stretch; flex-direction: column; }.hero-card, .section-card, .finalize-card { padding: var(--sp-4); }.evaluation-grid, .aar-grid, .action-card { grid-template-columns: minmax(0, 1fr); }.timeline-title .date { width: 100%; margin-left: 0; } }
@media (prefers-reduced-motion: reduce) { .back-link { transition: none; } }
</style>
