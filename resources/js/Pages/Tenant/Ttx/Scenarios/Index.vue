<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({ scenarios: { type: Array, default: () => [] }, capabilities: { type: Array, default: () => [] } });
const page = usePage();
const processingId = ref(null);
const loading = ref(false);
const loadError = ref('');
const errors = computed(() => page.props.errors ?? {});
const capabilityLabel = (code) => props.capabilities.find((item) => item.code === code)?.label ?? code;

function instantiate(id) {
    processingId.value = id;
    router.post(route('tenant.ttx.scenarios.instantiate', id), {}, {
        onFinish: () => { processingId.value = null; },
    });
}

function refresh() {
    router.reload({
        only: ['scenarios'],
        onStart: () => { loading.value = true; loadError.value = ''; },
        onFinish: () => { loading.value = false; },
        onError: () => { loadError.value = 'Katalog tidak dapat dimuat. Coba lagi.'; },
    });
}
</script>

<template>
    <Head title="Katalog Skenario Tabletop" />
    <AppLayout title="Katalog Skenario Tabletop">
        <main class="fade-in space-y-6">
            <header class="card flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: var(--brand)">Katalog Skenario</p>
                    <h1 class="font-display mt-2 text-2xl t-ink">Pilih skenario latihan</h1>
                    <p class="mt-2 max-w-2xl text-sm t-muted">Salin template terbit ke organisasi Anda, lalu pilih Playbook sendiri saat menyiapkan sesi.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="loading" @click="refresh">{{ loading ? 'Memuat...' : 'Muat ulang' }}</button>
                    <Link :href="route('tenant.ttx.exercises.index')" class="btn btn-primary focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px">Exercise saya</Link>
                </div>
            </header>

            <p v-if="loadError || Object.keys(errors).length" role="alert" class="card badge-danger p-4 text-sm">{{ loadError || Object.values(errors)[0] }}</p>
            <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Memuat katalog">
                <div v-for="n in 3" :key="n" class="card skeleton h-48" />
            </div>
            <div v-else-if="props.scenarios.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in props.scenarios" :key="item.id" class="card flex flex-col gap-4 p-6">
                    <h2 class="font-display text-lg t-ink">{{ item.title }}</h2>
                    <p class="line-clamp-4 whitespace-pre-line text-sm t-muted">{{ item.scenario }}</p>
                    <p class="text-sm t-muted">{{ item.active_inject_count }} inject aktif</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="code in item.capability_codes ?? []" :key="code" class="badge chip-brand" :title="capabilityLabel(code)">{{ code }}</span>
                    </div>
                    <button type="button" class="btn btn-primary mt-auto focus-visible:ring-2 focus-visible:ring-[var(--brand)] active:translate-y-px" :disabled="processingId === item.id" @click="instantiate(item.id)">{{ processingId === item.id ? 'Membuat Exercise...' : 'Gunakan skenario' }}</button>
                </article>
            </div>
            <div v-else class="card"><EmptyState title="Belum ada skenario terbit" message="Skenario yang tersedia akan muncul di sini setelah diterbitkan oleh Platform." /></div>
        </main>
    </AppLayout>
</template>
