<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { BaseAlert, BaseBadge, BaseButton, BaseInput, BaseSelect, BaseTableContainer, BaseTextarea } from '@/Components';
import EmptyState from '@/Components/EmptyState.vue';

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
    beginner: 'success',
    intermediate: 'warning',
    advanced: 'danger',
}[d] ?? 'neutral');

const statusBadge = (status) => {
    const map = {
        draft: 'neutral',
        published: 'success',
        archived: 'warning',
    };
    return map[status] || 'neutral';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Published', archived: 'Archived' };
    return map[status] || status;
};
</script>

<template>
    <Head title="Case Studies" />

    <AppLayout title="Case Study Builder">
        <BaseAlert v-if="errors.action" variant="danger" class="mb-4">{{ errors.action }}</BaseAlert>
        <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
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

        <BaseTableContainer>
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
                        <td colspan="6"><EmptyState message="Tidak ada case study dengan status ini." /></td>
                    </tr>
                    <tr v-for="c in filteredCases" :key="c.id" class="border-b b-line hover:bg-app/50 transition-colors">
                        <td class="px-6 py-3">
                            <Link :href="route('platform.cases.show', c.id)" class="font-medium transition-colors hover:underline focus-visible:outline-none focus-visible:ring-2" style="color: var(--brand)">
                                {{ c.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-3">
                            <BaseBadge :variant="difficultyBadge(c.difficulty)">
                                {{ c.difficulty }}
                            </BaseBadge>
                        </td>
                        <td class="px-6 py-3">
                            <BaseBadge :variant="statusBadge(c.status)">
                                {{ statusLabel(c.status) }}
                            </BaseBadge>
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
        </BaseTableContainer>
    </AppLayout>
</template>
