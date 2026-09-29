<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ scenario: { type: Object, default: null }, capabilities: { type: Array, default: () => [] } });
const page = usePage();
const saved = computed(() => !!props.scenario);
const statusLabel = { draft: 'Draft', published: 'Terbit', archived: 'Arsip' };
const editable = computed(() => !saved.value || props.scenario.status !== 'archived');
const activeInjects = computed(() => (props.scenario?.injects ?? []).filter((inject) => inject.status === 'active'));
const archivedInjects = computed(() => (props.scenario?.injects ?? []).filter((inject) => inject.status === 'archived'));
const serverErrors = computed(() => page.props.errors ?? {});
const form = useForm({
    title: props.scenario?.title ?? '', scenario: props.scenario?.scenario ?? '',
    objectives: props.scenario?.objectives ?? '', scope: props.scenario?.scope ?? '',
    capability_codes: props.scenario?.capability_codes ?? [],
});
const injectForm = useForm({ title: '', description: '', capability_codes: [] });
const editingInjectId = ref(null);
const statusBusy = ref(false);
const movingId = ref(null);

function toggleCode(target, code) {
    target.capability_codes = target.capability_codes.includes(code)
        ? target.capability_codes.filter((value) => value !== code)
        : [...target.capability_codes, code];
}

function saveScenario() {
    if (saved.value) form.put(route('platform.ttx.scenarios.update', props.scenario.id), { preserveScroll: true });
    else form.post(route('platform.ttx.scenarios.store'));
}

function editInject(inject) {
    editingInjectId.value = inject.id;
    injectForm.title = inject.title;
    injectForm.description = inject.description ?? '';
    injectForm.capability_codes = [...(inject.capability_codes ?? [])];
    injectForm.clearErrors();
}

function resetInject() {
    editingInjectId.value = null;
    injectForm.reset();
    injectForm.clearErrors();
}

function saveInject() {
    const options = { preserveScroll: true, onSuccess: resetInject };
    if (editingInjectId.value) injectForm.put(route('platform.ttx.scenarios.injects.update', [props.scenario.id, editingInjectId.value]), options);
    else injectForm.post(route('platform.ttx.scenarios.injects.store', props.scenario.id), options);
}

function archiveInject(inject) {
    statusBusy.value = true;
    router.delete(route('platform.ttx.scenarios.injects.archive', [props.scenario.id, inject.id]), {
        preserveScroll: true, onFinish: () => { statusBusy.value = false; },
    });
}

function moveInject(index, direction) {
    const ids = activeInjects.value.map((inject) => inject.id);
    const next = index + direction;
    if (next < 0 || next >= ids.length) return;
    [ids[index], ids[next]] = [ids[next], ids[index]];
    movingId.value = ids[next];
    router.post(route('platform.ttx.scenarios.injects.reorder', props.scenario.id), { inject_ids: ids }, {
        preserveScroll: true, onFinish: () => { movingId.value = null; },
    });
}

function changeStatus(action) {
    statusBusy.value = true;
    router.post(route(`platform.ttx.scenarios.${action}`, props.scenario.id), {}, {
        preserveScroll: true, onFinish: () => { statusBusy.value = false; },
    });
}
</script>

<template>
    <Head :title="saved ? 'Edit Skenario Tabletop' : 'Buat Skenario Tabletop'" />
    <AppLayout title="Katalog Skenario Tabletop">
        <main class="fade-in space-y-6">
            <header class="card p-6 sm:p-8">
                <Link :href="route('platform.ttx.scenarios.index')" class="text-sm font-semibold t-muted hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:opacity-75">Kembali ke katalog</Link>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: var(--brand)">Editor Platform</p>
                        <h1 class="font-display mt-2 text-2xl t-ink">{{ saved ? props.scenario.title : 'Skenario baru' }}</h1>
                        <p class="mt-2 text-sm t-muted">Status: {{ saved ? statusLabel[props.scenario.status] : 'Draft' }}</p>
                    </div>
                    <div v-if="saved && editable" class="flex flex-wrap gap-2">
                        <button v-if="props.scenario.status === 'draft'" type="button" class="btn btn-primary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="statusBusy" @click="changeStatus('publish')">{{ statusBusy ? 'Memproses...' : 'Terbitkan' }}</button>
                        <button type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="statusBusy" @click="changeStatus('archive')">{{ statusBusy ? 'Memproses...' : 'Arsipkan' }}</button>
                    </div>
                </div>
            </header>

            <p v-if="Object.keys(serverErrors).length" role="alert" class="card badge-danger p-4 text-sm">{{ Object.values(serverErrors)[0] }}</p>
            <form class="card grid gap-5 p-6 sm:grid-cols-2" @submit.prevent="saveScenario">
                <div class="sm:col-span-2">
                    <label for="scenario-title" class="text-sm font-semibold t-ink">Judul skenario</label>
                    <input id="scenario-title" v-model="form.title" class="input mt-2" maxlength="255" required :disabled="!editable" />
                    <p v-if="form.errors.title" role="alert" class="mt-1 text-sm badge-danger">{{ form.errors.title }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="scenario-body" class="text-sm font-semibold t-ink">Narasi skenario</label>
                    <textarea id="scenario-body" v-model="form.scenario" class="input mt-2" rows="5" maxlength="10000" :disabled="!editable" />
                    <p v-if="form.errors.scenario" role="alert" class="mt-1 text-sm badge-danger">{{ form.errors.scenario }}</p>
                </div>
                <div>
                    <label for="scenario-objectives" class="text-sm font-semibold t-ink">Tujuan latihan</label>
                    <textarea id="scenario-objectives" v-model="form.objectives" class="input mt-2" rows="3" maxlength="5000" :disabled="!editable" />
                </div>
                <div>
                    <label for="scenario-scope" class="text-sm font-semibold t-ink">Ruang lingkup</label>
                    <input id="scenario-scope" v-model="form.scope" class="input mt-2" maxlength="100" :disabled="!editable" />
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="text-sm font-semibold t-ink">Capability skenario</legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="capability in props.capabilities" :key="capability.code" class="card flex cursor-pointer gap-3 p-3 text-sm t-ink hover:shadow-md focus-within:ring-2 focus-within:ring-[var(--brand)] active:opacity-75">
                            <input type="checkbox" :checked="form.capability_codes.includes(capability.code)" :disabled="!editable" @change="toggleCode(form, capability.code)" />
                            <span><strong>{{ capability.code }}</strong> {{ capability.label }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.capability_codes" role="alert" class="mt-2 text-sm badge-danger">{{ form.errors.capability_codes }}</p>
                </fieldset>
                <div v-if="editable" class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="btn btn-primary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="form.processing">{{ form.processing ? 'Menyimpan...' : 'Simpan skenario' }}</button>
                </div>
            </form>

            <section v-if="saved" class="space-y-4" aria-labelledby="inject-heading">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 id="inject-heading" class="font-display text-xl t-ink">Inject</h2>
                        <p class="text-sm t-muted">Gunakan tombol naik/turun untuk mengatur urutan aktif.</p>
                    </div>
                    <span class="badge badge-ok">{{ activeInjects.length }} aktif</span>
                </div>
                <div v-if="!activeInjects.length" class="card"><EmptyState title="Belum ada inject aktif" message="Tambahkan inject untuk dapat menerbitkan skenario." /></div>
                <div v-for="(inject, index) in activeInjects" :key="inject.id" class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold t-muted">INJECT {{ index + 1 }}</p>
                        <h3 class="font-display mt-1 t-ink">{{ inject.title }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm t-muted">{{ inject.description }}</p>
                        <p class="mt-2 text-xs t-muted">{{ (inject.capability_codes ?? []).join(', ') || 'Belum ada capability' }}</p>
                    </div>
                    <div v-if="editable" class="flex shrink-0 flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="index === 0 || movingId !== null" :aria-label="`Naikkan ${inject.title}`" @click="moveInject(index, -1)">Naik</button>
                        <button type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="index === activeInjects.length - 1 || movingId !== null" :aria-label="`Turunkan ${inject.title}`" @click="moveInject(index, 1)">Turun</button>
                        <button type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" @click="editInject(inject)">Edit</button>
                        <button type="button" class="btn btn-ghost focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="statusBusy" @click="archiveInject(inject)">{{ statusBusy ? 'Memproses...' : 'Arsipkan' }}</button>
                    </div>
                </div>
                <form v-if="editable" class="card grid gap-4 p-6" @submit.prevent="saveInject">
                    <h3 class="font-display text-lg t-ink">{{ editingInjectId ? 'Edit inject' : 'Tambah inject' }}</h3>
                    <div>
                        <label for="inject-title" class="text-sm font-semibold t-ink">Judul inject</label>
                        <input id="inject-title" v-model="injectForm.title" class="input mt-2" maxlength="255" required />
                        <p v-if="injectForm.errors.title" role="alert" class="mt-1 text-sm badge-danger">{{ injectForm.errors.title }}</p>
                    </div>
                    <div>
                        <label for="inject-description" class="text-sm font-semibold t-ink">Isi inject</label>
                        <textarea id="inject-description" v-model="injectForm.description" class="input mt-2" rows="4" maxlength="10000" />
                    </div>
                    <fieldset>
                        <legend class="text-sm font-semibold t-ink">Capability inject</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label v-for="capability in props.capabilities" :key="capability.code" class="chip cursor-pointer focus-within:ring-2 focus-within:ring-[var(--brand)] active:opacity-75">
                                <input type="checkbox" :checked="injectForm.capability_codes.includes(capability.code)" @change="toggleCode(injectForm, capability.code)" />
                                {{ capability.code }}
                            </label>
                        </div>
                        <p v-if="injectForm.errors.capability_codes || injectForm.errors.injects" role="alert" class="mt-2 text-sm badge-danger">{{ injectForm.errors.capability_codes || injectForm.errors.injects }}</p>
                    </fieldset>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="injectForm.processing">{{ injectForm.processing ? 'Menyimpan...' : editingInjectId ? 'Simpan inject' : 'Tambah inject' }}</button>
                        <button v-if="editingInjectId" type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" @click="resetInject">Batal</button>
                    </div>
                </form>
                <details v-if="archivedInjects.length" class="card p-5 text-sm t-muted">
                    <summary class="cursor-pointer hover:underline focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:opacity-75">{{ archivedInjects.length }} inject diarsipkan</summary>
                    <ul class="mt-3 list-inside list-disc"><li v-for="inject in archivedInjects" :key="inject.id">{{ inject.title }}</li></ul>
                </details>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.input:hover, .input:active { border-color: var(--brand); }
.input:focus-visible, input[type='checkbox']:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
input[type='checkbox'] { accent-color: var(--brand); transition: outline-color 150ms ease; }
input[type='checkbox']:hover, input[type='checkbox']:active { outline: 1px solid var(--brand); }
</style>
