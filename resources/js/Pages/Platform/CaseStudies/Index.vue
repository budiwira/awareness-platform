<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseButton, BaseInput, BaseSelect, BaseTextarea } from '@/Components';

defineProps({ cases: Array });

const errors = computed(() => usePage().props.errors ?? {});

const statusFilter = ref('all');
const processing = ref(null);
const submitting = ref(false);

const filteredCases = computed(() => {
    const data = usePage().props.cases;
    if (statusFilter.value === 'all') return data;
    return data.filter(c => c.status === statusFilter.value);
});

const statusCounts = computed(() => {
    const data = usePage().props.cases;
    return {
        all: data.length,
        draft: data.filter(c => c.status === 'draft').length,
        published: data.filter(c => c.status === 'published').length,
        archived: data.filter(c => c.status === 'archived').length,
    };
});

const showForm = ref(false);
const form = ref({ title: '', description: '', difficulty: 'beginner', duration_minutes: 15, status: 'draft' });

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    router.post(route('platform.cases.store'), form.value, {
        onSuccess: () => (showForm.value = false),
        onFinish: () => submitting.value = false,
    });
};

const publish = (id) => {
    if (processing.value) return;
    processing.value = `publish:${id}`;
    router.post(route('platform.cases.publish', id), {}, {
        onFinish: () => processing.value = null,
    });
};

const archive = (id) => {
    if (confirm('Arsipkan case study ini?')) {
        if (processing.value) return;
        processing.value = `archive:${id}`;
        router.post(route('platform.cases.archive', id), {}, {
            onFinish: () => processing.value = null,
        });
    }
};

const difficultyBadge = (d) => ({
    beginner: 'badge-ok',
    intermediate: 'badge-warn',
    advanced: 'badge-danger',
}[d] ?? 'bg-surface2 t-ink');

const statusBadge = (status) => {
    const map = {
        draft: 'bg-surface2 t-ink',
        published: 'badge-ok',
        archived: 'badge-warn',
    };
    return map[status] || 'bg-surface2 t-ink';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Published', archived: 'Archived' };
    return map[status] || status;
};
</script>

<template>
    <Head title="Case Studies" />

    <AppLayout title="Case Study Builder">
        <div v-if="errors.action" class="mb-4 rounded-xl border p-4 text-sm" style="border-color: var(--danger); background: var(--danger-bg); color: var(--danger)" role="alert">{{ errors.action }}</div>
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Buat latihan pengambilan keputusan berbasis skenario insiden.
            </p>
            <BaseButton
                @click="showForm = !showForm"
                :disabled="submitting"
            >
                + Buat Case Study
            </BaseButton>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <BaseInput v-model="form.title" class="md:col-span-2" label="Judul" required :error="errors.title" :disabled="submitting" />
                <BaseTextarea v-model="form.description" class="md:col-span-2" label="Ringkasan Skenario" :rows="2" :error="errors.description" :disabled="submitting" />
                <BaseSelect v-model="form.difficulty" label="Tingkat Kesulitan" :error="errors.difficulty" :disabled="submitting">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                </BaseSelect>
                <BaseInput v-model.number="form.duration_minutes" label="Durasi (menit)" type="number" required :error="errors.duration_minutes" :disabled="submitting" />
                <div class="md:col-span-2 flex justify-end">
                    <BaseButton type="submit" :loading="submitting">{{ submitting ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                </div>
            </form>
        </div>

        <!-- Filter chips -->
        <div class="flex gap-2 mb-6">
            <button
                v-for="(count, key) in statusCounts"
                :key="key"
                @click="statusFilter = key"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-all"
                :class="statusFilter === key ? 'chip-active shadow-md' : 'bg-surface t-ink hover:bg-app border b-line'"
            >
                {{ key === 'all' ? 'Semua' : statusLabel(key) }} <span class="opacity-75">({{ count }})</span>
            </button>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left t-muted border-b b-line">
                        <th class="px-6 py-3 font-medium">Judul</th>
                        <th class="px-6 py-3 font-medium">Kesulitan</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Scene</th>
                        <th class="px-6 py-3 font-medium">Durasi</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filteredCases.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center t-muted">
                            <div class="flex flex-col items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div class="text-sm">Tidak ada case study dengan status ini.</div>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="c in filteredCases" :key="c.id" class="border-b b-line hover:bg-app/50 transition-colors">
                        <td class="px-6 py-3">
                            <Link :href="route('platform.cases.show', c.id)" class="text-indigo-600 font-medium hover:underline">
                                {{ c.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="difficultyBadge(c.difficulty)">
                                {{ c.difficulty }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(c.status)">
                                {{ statusLabel(c.status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ c.scenes_count }}</td>
                        <td class="px-6 py-3 t-muted">{{ c.duration_minutes }} menit</td>
                        <td class="px-6 py-3 text-right space-x-3">
                            <BaseButton v-if="c.status === 'draft'" size="sm" variant="secondary" :loading="processing === `publish:${c.id}`" :disabled="processing !== null && processing !== `publish:${c.id}`" @click="publish(c.id)">{{ processing === `publish:${c.id}` ? 'Memproses...' : 'Terbitkan' }}</BaseButton>
                            <BaseButton v-if="c.status === 'published'" size="sm" variant="danger" :loading="processing === `archive:${c.id}`" :disabled="processing !== null && processing !== `archive:${c.id}`" @click="archive(c.id)">{{ processing === `archive:${c.id}` ? 'Memproses...' : 'Arsipkan' }}</BaseButton>
                            <BaseButton v-if="c.status === 'archived'" size="sm" variant="secondary" :loading="processing === `publish:${c.id}`" :disabled="processing !== null && processing !== `publish:${c.id}`" @click="publish(c.id)">{{ processing === `publish:${c.id}` ? 'Memproses...' : 'Terbitkan' }}</BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
