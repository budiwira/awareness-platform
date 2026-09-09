<script setup>
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseButton, BaseCard } from '@/Components';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
    user: Object,
    modules: Array,
});

const localModules = ref(props.modules.map(m => ({ ...m })));
const saving = ref(false);

const toast = useToast();

const toggleModule = (module) => {
    module.is_allowed = !module.is_allowed;
};

const saveChanges = () => {
    saving.value = true;

    router.post(route('tenant.users.access.update', props.user.id), {
        module_ids: localModules.value.map(m => m.id),
        is_allowed: true,
    }, {
        onSuccess: () => {
            saving.value = false;
            toast.success('Perubahan akses berhasil disimpan.');
        },
        onError: () => {
            saving.value = false;
            toast.error('Gagal menyimpan perubahan. Silakan coba lagi.');
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

        <BaseCard>
            <template #header>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <h2 class="font-display text-xl font-bold t-ink">{{ user.name }}</h2>
                        <p class="text-sm t-muted">{{ user.email }}</p>
                    </div>
                    <BaseButton variant="primary" :loading="saving" @click="saveChanges">
                        Simpan Perubahan
                    </BaseButton>
                </div>
            </template>

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
                            :aria-label="`Toggle akses untuk ${module.title}`"
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
        </BaseCard>
    </AppLayout>
</template>