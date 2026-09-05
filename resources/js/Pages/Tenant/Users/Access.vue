<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
    modules: Array,
});

const localModules = ref(props.modules.map(m => ({ ...m })));
const saving = ref(false);
const savedMessage = ref('');

const toggleModule = (module) => {
    module.is_allowed = !module.is_allowed;
};

const saveChanges = () => {
    saving.value = true;
    savedMessage.value = '';

    const moduleIds = localModules.value.map(m => m.id);
    const allowedIds = localModules.value.filter(m => m.is_allowed).map(m => m.id);

    // Revoke modules yang tidak allowed
    const toRevoke = localModules.value.filter(m => !m.is_allowed).map(m => m.id);
    const toGrant = allowedIds;

    // Kirim satu request update untuk semua
    router.post(route('tenant.users.access.update', props.user.id), {
        module_ids: localModules.value.map(m => m.id),
        is_allowed: true, // default, akan di-override per module
    }, {
        onSuccess: () => {
            saving.value = false;
            savedMessage.value = 'Perubahan akses disimpan.';
            setTimeout(() => savedMessage.value = '', 3000);
        },
        onError: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Kelola Akses - ${user.name}`" />

    <AppLayout :title="`Kelola Akses Modul: ${user.name}`">
        <div class="mb-6">
            <Link :href="route('tenant.users.index')" class="text-indigo-600 hover:underline text-sm">
                ? Kembali ke daftar user
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-display text-xl font-bold t-ink">{{ user.name }}</h2>
                    <p class="text-sm t-muted">{{ user.email }}</p>
                </div>
                <button
                    @click="saveChanges"
                    :disabled="saving"
                    class="btn btn-primary"
                >
                    {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </button>
            </div>

            <p v-if="savedMessage" class="text-sm text-green-600 mb-4">{{ savedMessage }}</p>

            <p class="text-sm t-muted mb-6">
                Aktifkan atau nonaktifkan akses modul untuk user ini. Modul yang dinonaktifkan tidak akan muncul di dashboard user.
            </p>

            <!-- Daftar modul dengan toggle -->
            <div class="space-y-3">
                <div
                    v-for="module in localModules"
                    :key="module.id"
                    class="flex items-center justify-between p-4 rounded-lg border b-line"
                    :class="module.is_allowed ? 'bg-surface2 border-green-500/40' : 'bg-surface2 border-red-500/40'"
                >
                    <div class="flex-1">
                        <div class="font-medium t-ink">{{ module.title }}</div>
                        <div class="text-sm t-muted">{{ module.description || 'Tidak ada deskripsi' }}</div>
                        <div class="text-xs t-muted mt-1">Durasi: {{ module.duration_minutes }} menit</div>
                    </div>
                    <div class="ml-4">
                        <button
                            @click="toggleModule(module)"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="module.is_allowed ? 'bg-green-600' : 'bg-gray-300'"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="module.is_allowed ? 'translate-x-6' : 'translate-x-1'"
                            ></span>
                        </button>
                        <div class="text-xs text-center mt-1" :class="module.is_allowed ? 'text-green-600' : 'text-red-600'">
                            {{ module.is_allowed ? 'Aktif' : 'Dibatasi' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>