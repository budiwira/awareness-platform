<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { BaseAlert, BaseBadge, BaseButton, BaseInput, BaseSelect, BaseTextarea } from '@/Components';

const props = defineProps({ caseStudy: Object });

const errors = computed(() => usePage().props.errors ?? {});
const submitting = ref(false);

const form = ref({
    situation: '',
    options: [
        { text: '', quality: 'best', feedback: '' },
        { text: '', quality: 'poor', feedback: '' },
    ],
});

const addOption = () => {
    if (form.value.options.length >= 4) return;
    form.value.options.push({ text: '', quality: 'acceptable', feedback: '' });
};

const removeOption = (i) => {
    if (form.value.options.length <= 2) return;
    form.value.options.splice(i, 1);
};

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    router.post(route('platform.cases.scenes.store', props.caseStudy.id), form.value, {
        onSuccess: () => {
            form.value = {
                situation: '',
                options: [
                    { text: '', quality: 'best', feedback: '' },
                    { text: '', quality: 'poor', feedback: '' },
                ],
            };
        },
        onFinish: () => (submitting.value = false),
    });
};

const qualityVariant = (q) => ({
    best: 'success',
    acceptable: 'warning',
    poor: 'danger',
}[q] ?? 'neutral');
</script>

<template>
    <Head :title="caseStudy.title" />

    <AppLayout :title="caseStudy.title">
        <div class="mb-6">
            <Link :href="route('platform.cases.index')" class="text-sm hover:underline focus-visible:outline-none focus-visible:ring-2" style="color: var(--brand)">
                ← Kembali ke Daftar Case Study
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <h2 class="text-xl font-bold t-ink">{{ caseStudy.title }}</h2>
            <p class="text-sm t-muted mt-1">{{ caseStudy.description }}</p>
            <p class="text-xs t-muted mt-2">{{ caseStudy.difficulty }} · {{ caseStudy.duration_minutes }} menit · {{ caseStudy.scenes?.length ?? 0 }} scene</p>
        </div>

        <!-- Daftar scene (quality & feedback HANYA untuk super admin) -->
        <div class="space-y-4 mb-8">
            <div v-for="(scene, si) in caseStudy.scenes" :key="scene.id" class="card p-6">
                <div class="font-medium t-ink mb-3">Scene {{ si + 1 }}: {{ scene.situation }}</div>
                <ul class="space-y-2">
                    <li v-for="(opt, oi) in scene.options" :key="oi" class="text-sm border b-line rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="t-ink">{{ String.fromCharCode(65 + oi) }}. {{ opt.text }}</span>
                            <BaseBadge :variant="qualityVariant(opt.quality)" size="sm">
                                {{ opt.quality }}
                            </BaseBadge>
                        </div>
                        <p class="text-xs t-muted mt-1">{{ opt.feedback }}</p>
                    </li>
                </ul>
            </div>
            <div v-if="(caseStudy.scenes?.length ?? 0) === 0" class="card"><EmptyState title="Belum ada scene" message="Tambahkan scene pertama melalui formulir di bawah." /></div>
        </div>

        <!-- Form tambah scene -->
        <div class="card p-6">
            <div class="font-semibold t-ink mb-4">Tambah Scene</div>
            <form @submit.prevent="submit" class="space-y-4">
                <BaseTextarea v-model="form.situation" label="Situasi" :rows="2" required :error="errors.situation" :disabled="submitting" />

                <div>
                    <label class="text-sm t-muted">Opsi Keputusan (2–4)</label>
                    <div class="mt-1 space-y-3">
                        <div v-for="(opt, i) in form.options" :key="i" class="border b-line rounded-lg p-3 space-y-2">
                            <BaseInput v-model="opt.text" :label="'Opsi ' + String.fromCharCode(65 + i)" required :placeholder="'Teks opsi ' + String.fromCharCode(65 + i)" :disabled="submitting" />
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <BaseSelect v-model="opt.quality" label="Kualitas" :disabled="submitting">
                                    <option value="best">Best (terbaik)</option>
                                    <option value="acceptable">Acceptable (dapat diterima)</option>
                                    <option value="poor">Poor (buruk)</option>
                                </BaseSelect>
                                <BaseInput v-model="opt.feedback" label="Feedback" required placeholder="Feedback setelah dipilih" :disabled="submitting" />
                            </div>
                            <BaseButton v-if="form.options.length > 2" size="sm" variant="danger" :disabled="submitting" @click="removeOption(i)">Hapus opsi</BaseButton>
                        </div>
                    </div>
                    <BaseButton class="mt-2" size="sm" variant="secondary" :disabled="submitting || form.options.length >= 4" @click="addOption">+ Tambah Opsi</BaseButton>
                    <BaseAlert v-if="errors.options" variant="danger" class="mt-2">{{ errors.options }}</BaseAlert>
                </div>

                <div class="flex justify-end">
                    <BaseButton type="submit" :loading="submitting">{{ submitting ? 'Menyimpan...' : 'Simpan Scene' }}</BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
