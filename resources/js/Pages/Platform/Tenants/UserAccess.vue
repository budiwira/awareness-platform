<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tenant: Object,
    users: Array,
});

const selectedUserId = ref(props.users[0]?.user_id ?? null);
const saving = ref(false);
const savedMessage = ref('');

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
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [, ''])[1]),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(data),
    });

    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message || ('HTTP ' + res.status));
    }

    return body;
};

const saveModules = async () => {
    if (!selectedUser.value) return;
    saving.value = true;
    savedMessage.value = '';

    try {
        const mods = selectedUser.value.modules;
        const allowedIds = mods.filter(m => m.is_allowed).map(m => m.module_id);
        const deniedIds = mods.filter(m => !m.is_allowed).map(m => m.module_id);
        const url = route('platform.tenants.user-access.update', props.tenant.id);

        if (allowedIds.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, module_ids: allowedIds, is_allowed: true });
        }
        if (deniedIds.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, module_ids: deniedIds, is_allowed: false });
        }

        savedMessage.value = 'Perubahan akses modul disimpan.';
    } catch (e) {
        savedMessage.value = 'Gagal menyimpan: ' + e.message;
    } finally {
        saving.value = false;
        setTimeout(() => savedMessage.value = '', 3000);
    }
};

const saveFeatures = async () => {
    if (!selectedUser.value) return;
    saving.value = true;
    savedMessage.value = '';

    try {
        const feats = selectedUser.value.features;
        const allowedKeys = feats.filter(f => f.is_allowed).map(f => f.key);
        const deniedKeys = feats.filter(f => !f.is_allowed).map(f => f.key);
        const url = route('platform.tenants.user-access.update', props.tenant.id);

        if (allowedKeys.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, feature_keys: allowedKeys, is_allowed: true });
        }
        if (deniedKeys.length > 0) {
            await postJson(url, { user_id: selectedUser.value.user_id, feature_keys: deniedKeys, is_allowed: false });
        }

        savedMessage.value = 'Perubahan akses fitur disimpan.';
    } catch (e) {
        savedMessage.value = 'Gagal menyimpan: ' + e.message;
    } finally {
        saving.value = false;
        setTimeout(() => savedMessage.value = '', 3000);
    }
};

const featureLabel = (key) => {
    const labels = {
        'phishing': 'Phishing Simulation',
        'ttx': 'Tabletop Exercise',
        'ctf': 'Capture The Flag',
        'case_studies': 'Case Studies',
    };
    return labels[key] || key;
};
</script>

<template>
    <Head :title="`Akses User - ${tenant.name}`" />

    <AppLayout :title="`Kelola Akses User: ${tenant.name}`">
        <div class="mb-6">
            <Link :href="route('platform.tenants.index')" class="text-indigo-600 hover:underline text-sm">
                ? Kembali ke daftar tenant
            </Link>
        </div>

        <!-- User Selector -->
        <div class="card p-6 mb-6">
            <label class="text-sm t-muted block mb-2">Pilih User</label>
            <select v-model="selectedUserId" class="input w-full md:w-96">
                <option v-for="user in users" :key="user.user_id" :value="user.user_id">
                    {{ user.name }} ({{ user.email }}) - {{ user.role }}
                </option>
            </select>
        </div>

        <div v-if="selectedUser" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Module Access -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-lg font-bold t-ink">Akses Modul</h3>
                    <button
                        @click="saveModules"
                        :disabled="saving"
                        class="btn btn-primary text-sm"
                    >
                        {{ saving ? 'Menyimpan...' : 'Simpan Modul' }}
                    </button>
                </div>

                <p class="text-sm t-muted mb-4">
                    Modul yang dinonaktifkan tidak akan muncul di dashboard user ini.
                </p>

                <div class="space-y-2">
                    <div
                        v-for="mod in selectedUser.modules"
                        :key="mod.module_id"
                        class="flex items-center justify-between p-3 rounded-lg border b-line"
                        :class="mod.is_allowed ? 'bg-surface2 border-green-500/40' : 'bg-surface2 border-red-500/40'"
                    >
                        <div class="font-medium t-ink">{{ mod.title }}</div>
                        <button
                            @click="toggleModule(mod)"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="mod.is_allowed ? 'bg-green-600 dark:bg-green-500' : 'bg-gray-300 dark:bg-gray-700'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white dark:bg-gray-100 transition-transform"
                                :class="mod.is_allowed ? 'translate-x-6' : 'translate-x-1'"
                            ></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Feature Access -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-lg font-bold t-ink">Akses Fitur</h3>
                    <button
                        @click="saveFeatures"
                        :disabled="saving"
                        class="btn btn-primary text-sm"
                    >
                        {{ saving ? 'Menyimpan...' : 'Simpan Fitur' }}
                    </button>
                </div>

                <p class="text-sm t-muted mb-4">
                    Fitur yang dinonaktifkan tidak dapat diakses user ini (phishing, TTX, CTF, dll).
                </p>

                <div class="space-y-2">
                    <div
                        v-for="feat in selectedUser.features"
                        :key="feat.key"
                        class="flex items-center justify-between p-3 rounded-lg border b-line"
                        :class="feat.is_allowed ? 'bg-surface2 border-green-500/40' : 'bg-surface2 border-red-500/40'"
                    >
                        <div class="font-medium t-ink">{{ featureLabel(feat.key) }}</div>
                        <button
                            @click="toggleFeature(feat)"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="feat.is_allowed ? 'bg-green-600 dark:bg-green-500' : 'bg-gray-300 dark:bg-gray-700'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white dark:bg-gray-100 transition-transform"
                                :class="feat.is_allowed ? 'translate-x-6' : 'translate-x-1'"
                            ></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="savedMessage" class="text-sm text-green-600 dark:text-green-400 mt-4">{{ savedMessage }}</p>
    </AppLayout>
</template>
