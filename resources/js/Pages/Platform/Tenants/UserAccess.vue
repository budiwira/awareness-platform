<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseAlert from '@/Components/BaseAlert.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    tenant: Object,
    users: Array,
});

const selectedUserId = ref(props.users[0]?.user_id ?? null);
const saving = ref(false);
const savedMessage = ref('');
const messageType = ref('success');

const selectedUser = computed(() =>
    props.users.find(u => u.user_id === selectedUserId.value)
);

const toggleModule = (mod) => {
    mod.is_allowed = !mod.is_allowed;
};

const toggleFeature = (feat) => {
    feat.is_allowed = !feat.is_allowed;
};

const postJson = async (url, data) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [, ''])[1]),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(data),
    });

    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
        const validationMessage = Object.values(body.errors ?? {}).flat()[0];
        throw new Error(body.message || body.error || validationMessage || ('HTTP ' + res.status));
    }

    return body;
};

const saveChanges = async () => {
    if (!selectedUser.value || saving.value) return;
    saving.value = true;
    savedMessage.value = '';

    try {
        const mods = selectedUser.value.modules;
        const feats = selectedUser.value.features;
        const url = route('platform.tenants.user-access.update', props.tenant.id);

        const allowedModuleIds = mods.filter(m => m.is_allowed).map(m => m.module_id);
        const deniedModuleIds = mods.filter(m => !m.is_allowed).map(m => m.module_id);
        if (allowedModuleIds.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, module_ids: allowedModuleIds, is_allowed: true });
        }
        if (deniedModuleIds.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, module_ids: deniedModuleIds, is_allowed: false });
        }

        const allowedFeatureKeys = feats.filter(f => f.is_allowed).map(f => f.key);
        const deniedFeatureKeys = feats.filter(f => !f.is_allowed).map(f => f.key);
        if (allowedFeatureKeys.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, feature_keys: allowedFeatureKeys, is_allowed: true });
        }
        if (deniedFeatureKeys.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, feature_keys: deniedFeatureKeys, is_allowed: false });
        }

        messageType.value = 'success';
        savedMessage.value = 'Perubahan akses berhasil disimpan.';
    } catch (e) {
        messageType.value = 'error';
        savedMessage.value = 'Gagal menyimpan: ' + e.message;
    } finally {
        saving.value = false;
    }
};

const featureLabel = (key) => {
    const labels = {
        'training': 'Pelatihan',
        'reports_export': 'Ekspor laporan',
        'phishing': 'Simulasi phishing',
        'ttx': 'Tabletop Exercise',
        'ctf': 'Capture The Flag',
        'case_studies': 'Studi kasus',
    };
    return labels[key] || key;
};
</script>

<template>
    <Head :title="`Akses pengguna - ${tenant.name}`" />
    <AppLayout title="Akses pengguna">
        <div class="access-page fade-in space-y-6">
            <Link :href="route('platform.tenants.index')" class="btn btn-secondary access-link">Kembali ke organisasi</Link>
            <section class="card p-5 sm:p-6" aria-labelledby="access-heading">
                <p class="text-xs font-semibold uppercase tracking-wider t-muted">Dukungan organisasi</p>
                <h2 id="access-heading" class="font-display text-2xl t-ink mt-2">{{ tenant.name }}</h2>
                <p class="text-sm t-muted mt-2">Kelola akses modul dan fitur untuk pengguna organisasi ini.</p>
                <BaseSelect v-if="users.length" id="access-user" v-model="selectedUserId" class="mt-5" label="Pilih pengguna" :disabled="saving">
                    <option v-for="user in users" :key="user.user_id" :value="user.user_id">{{ user.name }} — {{ user.email }}</option>
                </BaseSelect>
                <div v-if="selectedUser" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="t-ink font-medium">{{ selectedUser.name }}</span>
                    <BaseBadge>{{ selectedUser.role === 'tenant_admin' ? 'Admin organisasi' : selectedUser.role === 'super_admin' ? 'Super Admin' : 'Peserta' }}</BaseBadge>
                    <span class="t-muted">{{ selectedUser.email }}</span>
                </div>
            </section>
            <BaseAlert v-if="savedMessage" :variant="messageType === 'success' ? 'success' : 'danger'">{{ savedMessage }}</BaseAlert>
            <EmptyState v-if="!users.length" class="card" title="Belum ada pengguna" message="Organisasi ini belum memiliki pengguna yang dapat dikelola." />
            <template v-else-if="selectedUser">
                <BaseAlert variant="info">Perubahan akses diterapkan setelah disimpan. Menonaktifkan akses akan membatasi modul atau fitur bagi pengguna terpilih.</BaseAlert>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <section class="card p-5 sm:p-6" aria-labelledby="modules-heading">
                        <h3 id="modules-heading" class="font-display text-lg t-ink">Akses modul</h3>
                        <p class="text-sm t-muted mt-2 mb-4">Modul yang dinonaktifkan tidak akan muncul di dashboard pengguna.</p>
                        <EmptyState v-if="!selectedUser.modules.length" title="Tidak ada modul" message="Belum ada modul yang tersedia dalam paket organisasi." />
                        <div v-else class="space-y-3">
                            <div v-for="mod in selectedUser.modules" :key="mod.module_id" class="access-row">
                                <span class="font-medium t-ink">{{ mod.title }}</span>
                                <BaseButton :variant="mod.is_allowed ? 'secondary' : 'danger'" :disabled="saving" role="switch" :aria-checked="mod.is_allowed" :aria-label="`Akses modul ${mod.title}`" @click="toggleModule(mod)">{{ mod.is_allowed ? 'Aktif' : 'Nonaktif' }}</BaseButton>
                            </div>
                        </div>
                    </section>
                    <section class="card p-5 sm:p-6" aria-labelledby="features-heading">
                        <h3 id="features-heading" class="font-display text-lg t-ink">Akses fitur</h3>
                        <p class="text-sm t-muted mt-2 mb-4">Fitur yang dinonaktifkan tidak dapat diakses pengguna.</p>
                        <EmptyState v-if="!selectedUser.features.length" title="Tidak ada fitur" message="Belum ada fitur yang tersedia dalam paket organisasi." />
                        <div v-else class="space-y-3">
                            <div v-for="feat in selectedUser.features" :key="feat.key" class="access-row">
                                <span class="font-medium t-ink">{{ featureLabel(feat.key) }}</span>
                                <BaseButton :variant="feat.is_allowed ? 'secondary' : 'danger'" :disabled="saving" role="switch" :aria-checked="feat.is_allowed" :aria-label="`Akses fitur ${featureLabel(feat.key)}`" @click="toggleFeature(feat)">{{ feat.is_allowed ? 'Aktif' : 'Nonaktif' }}</BaseButton>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="flex flex-wrap justify-end gap-3 items-center">
                    <p class="text-sm t-muted">Perubahan hanya untuk {{ selectedUser.name }}.</p>
                    <BaseButton :loading="saving" :disabled="!selectedUser.modules.length && !selectedUser.features.length" @click="saveChanges">{{ saving ? 'Menyimpan…' : 'Simpan perubahan' }}</BaseButton>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.access-page { min-width: 0; overflow-wrap: anywhere; animation-duration: 200ms; }
.access-link { min-height: 44px; }
.access-link:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.access-page :deep(select) { min-width: 0; }
.access-page :deep(select:hover) { border-color: var(--brand); }
.access-page :deep(select:active) { border-color: var(--brand-strong); }
.access-page :deep(select:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }
.access-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem; border: 1px solid var(--line); border-radius: var(--r-lg); background: var(--surface-2); }
.access-row > span { min-width: 0; }
.access-row :deep(button) { flex-shrink: 0; }
</style>
