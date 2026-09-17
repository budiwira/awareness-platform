<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { BaseAlert, BaseBadge, BaseButton, BaseTableContainer } from '@/Components';

defineProps({ challenges: Array });

const errors = computed(() => usePage().props.errors ?? {});

const statusFilter = ref('all');
const processing = ref(null);
const submitting = ref(false);

const filteredChallenges = computed(() => {
    const data = usePage().props.challenges;
    if (statusFilter.value === 'all') return data;
    return data.filter(c => c.status === statusFilter.value);
});

const statusCounts = computed(() => {
    const data = usePage().props.challenges;
    return {
        all: data.length,
        draft: data.filter(c => c.status === 'draft').length,
        published: data.filter(c => c.status === 'published').length,
        archived: data.filter(c => c.status === 'archived').length,
    };
});

const showForm = ref(false);
const form = ref({
    title: '',
    description: '',
    category: 'general',
    difficulty: 'beginner',
    points: 100,
    flag: '',
    hint: '',
    status: 'draft',
});

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    router.post(route('platform.ctf.store'), form.value, {
        onSuccess: () => (showForm.value = false),
        onFinish: () => submitting.value = false,
    });
};

const publish = (id) => {
    if (processing.value) return;
    processing.value = `publish:${id}`;
    router.post(route('platform.ctf.publish', id), {}, {
        onFinish: () => processing.value = null,
    });
};

const archive = (id) => {
    if (confirm('Arsipkan challenge ini?')) {
        if (processing.value) return;
        processing.value = `archive:${id}`;
        router.post(route('platform.ctf.archive', id), {}, {
            onFinish: () => processing.value = null,
        });
    }
};

const difficultyVariant = (d) => ({
    beginner: 'success',
    intermediate: 'warning',
    advanced: 'danger',
}[d] ?? 'neutral');

const statusVariant = (status) => {
    const map = {
        draft: 'neutral',
        published: 'success',
        archived: 'warning',
    };
    return map[status] || 'neutral';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Terbit', archived: 'Diarsipkan' };
    return map[status] || status;
};
</script>

<template>
    <Head title="CTF Challenges" />

    <AppLayout title="CTF Challenge Builder">
        <BaseAlert v-if="errors.action" variant="danger" class="mb-4">{{ errors.action }}</BaseAlert>
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm t-muted">
                Buat challenge gamifikasi. Flag diverifikasi server-side dan tidak pernah dikirim ke browser.
            </p>
            <BaseButton @click="showForm = !showForm" :disabled="submitting">
                Buat challenge
            </BaseButton>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Judul</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" />
                    <p v-if="errors.title" class="text-xs mt-1" style="color: var(--danger)" role="alert">{{ errors.title }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Deskripsi Tantangan</label>
                    <textarea v-model="form.description" rows="2" class="input mt-1 w-full"></textarea>
                </div>
                <div>
                    <label class="text-sm t-muted">Kategori</label>
                    <select v-model="form.category" class="input mt-1 w-full">
                        <option value="general">General</option>
                        <option value="phishing">Phishing</option>
                        <option value="password">Password</option>
                        <option value="social_engineering">Social Engineering</option>
                        <option value="web">Web</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm t-muted">Kesulitan</label>
                    <select v-model="form.difficulty" class="input mt-1 w-full">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm t-muted">Poin</label>
                    <input v-model.number="form.points" type="number" min="10" max="1000" required class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-sm t-muted">Flag (rahasia)</label>
                    <input v-model="form.flag" type="text" required placeholder="FLAG{contoh_flag}" class="input mt-1 w-full font-mono" />
                    <p v-if="errors.flag" class="text-xs mt-1" style="color: var(--danger)" role="alert">{{ errors.flag }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm t-muted">Hint (opsional)</label>
                    <input v-model="form.hint" type="text" class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <BaseButton type="submit" :loading="submitting" :disabled="submitting">{{ submitting ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
                </div>
            </form>
        </div>

        <!-- Filter chips -->
        <div class="flex gap-2 mb-6">
            <button
                v-for="(count, key) in statusCounts"
                :key="key"
                @click="statusFilter = key"
                class="min-h-[44px] rounded-lg px-4 py-2 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
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
                        <th class="px-6 py-3 font-medium">Kategori</th>
                        <th class="px-6 py-3 font-medium">Kesulitan</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                        <th class="px-6 py-3 font-medium">Poin</th>
                        <th class="px-6 py-3 font-medium">Solved</th>
                        <th class="px-6 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="filteredChallenges.length === 0">
                        <td colspan="7"><EmptyState message="Tidak ada challenge dengan status ini." /></td>
                    </tr>
                    <tr v-for="c in filteredChallenges" :key="c.id" class="border-b b-line hover:bg-app/50 transition-colors">
                        <td class="px-6 py-3 font-medium t-ink">{{ c.title }}</td>
                        <td class="px-6 py-3 t-muted">{{ c.category }}</td>
                        <td class="px-6 py-3">
                            <BaseBadge :variant="difficultyVariant(c.difficulty)">
                                {{ c.difficulty }}
                            </BaseBadge>
                        </td>
                        <td class="px-6 py-3">
                            <BaseBadge :variant="statusVariant(c.status)">
                                {{ statusLabel(c.status) }}
                            </BaseBadge>
                        </td>
                        <td class="px-6 py-3 t-muted">{{ c.points }}</td>
                        <td class="px-6 py-3 t-muted">{{ c.solves_count }}</td>
                        <td class="px-6 py-3 text-right space-x-3">
                            <BaseButton v-if="c.status === 'draft'" size="sm" variant="secondary" :loading="processing === `publish:${c.id}`" :disabled="processing !== null && processing !== `publish:${c.id}`" @click="publish(c.id)">{{ processing === `publish:${c.id}` ? 'Memproses...' : 'Terbitkan' }}</BaseButton>
                            <BaseButton v-if="c.status === 'published'" size="sm" variant="secondary" :loading="processing === `archive:${c.id}`" :disabled="processing !== null && processing !== `archive:${c.id}`" @click="archive(c.id)">{{ processing === `archive:${c.id}` ? 'Memproses...' : 'Arsipkan' }}</BaseButton>
                            <BaseButton v-if="c.status === 'archived'" size="sm" variant="secondary" :loading="processing === `publish:${c.id}`" :disabled="processing !== null && processing !== `publish:${c.id}`" @click="publish(c.id)">{{ processing === `publish:${c.id}` ? 'Memproses...' : 'Terbitkan' }}</BaseButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </BaseTableContainer>
    </AppLayout>
</template>
