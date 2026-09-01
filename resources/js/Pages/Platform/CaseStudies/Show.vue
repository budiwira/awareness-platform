<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ caseStudy: Object });

const errors = computed(() => usePage().props.errors ?? {});

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
    });
};

const qualityBadge = (q) => ({
    best: 'badge-ok',
    acceptable: 'bg-yellow-100 text-yellow-700',
    poor: 'bg-red-100 text-red-700',
}[q] ?? 'bg-surface2 t-ink');
</script>

<template>
    <Head :title="caseStudy.title" />

    <AppLayout :title="caseStudy.title">
        <div class="mb-6">
            <Link :href="route('platform.cases.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Case Study
            </Link>
        </div>

        <div class="card p-6 mb-6">
            <h2 class="text-xl font-bold t-ink">{{ caseStudy.title }}</h2>
            <p class="text-sm t-muted mt-1">{{ caseStudy.description }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ caseStudy.difficulty }} · {{ caseStudy.duration_minutes }} menit · {{ caseStudy.scenes?.length ?? 0 }} scene</p>
        </div>

        <!-- Daftar scene (quality & feedback HANYA untuk super admin) -->
        <div class="space-y-4 mb-8">
            <div v-for="(scene, si) in caseStudy.scenes" :key="scene.id" class="card p-6">
                <div class="font-medium t-ink mb-3">Scene {{ si + 1 }}: {{ scene.situation }}</div>
                <ul class="space-y-2">
                    <li v-for="(opt, oi) in scene.options" :key="oi" class="text-sm border b-line rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <span class="t-ink">{{ String.fromCharCode(65 + oi) }}. {{ opt.text }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium uppercase" :class="qualityBadge(opt.quality)">
                                {{ opt.quality }}
                            </span>
                        </div>
                        <p class="text-xs t-muted mt-1">{{ opt.feedback }}</p>
                    </li>
                </ul>
            </div>
            <div v-if="(caseStudy.scenes?.length ?? 0) === 0" class="card p-8 text-center t-muted text-sm">
                Belum ada scene. Tambahkan di bawah.
            </div>
        </div>

        <!-- Form tambah scene -->
        <div class="card p-6">
            <div class="font-semibold t-ink mb-4">Tambah Scene</div>
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="text-sm t-muted">Situasi</label>
                    <textarea v-model="form.situation" rows="2" required class="input mt-1 w-full"></textarea>
                    <p v-if="errors.situation" class="text-xs text-red-600 mt-1">{{ errors.situation }}</p>
                </div>

                <div>
                    <label class="text-sm t-muted">Opsi Keputusan (2–4)</label>
                    <div class="mt-1 space-y-3">
                        <div v-for="(opt, i) in form.options" :key="i" class="border b-line rounded-lg p-3 space-y-2">
                            <input v-model="opt.text" type="text" required :placeholder="'Teks opsi ' + String.fromCharCode(65 + i)" class="input w-full" />
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <select v-model="opt.quality" class="input">
                                    <option value="best">Best (terbaik)</option>
                                    <option value="acceptable">Acceptable (dapat diterima)</option>
                                    <option value="poor">Poor (buruk)</option>
                                </select>
                                <input v-model="opt.feedback" type="text" required placeholder="Feedback setelah dipilih" class="input" />
                            </div>
                            <button v-if="form.options.length > 2" type="button" @click="removeOption(i)" class="text-red-500 text-xs">Hapus opsi</button>
                        </div>
                    </div>
                    <button type="button" @click="addOption" class="mt-2 text-indigo-600 text-sm font-medium">+ Tambah Opsi</button>
                    <p v-if="errors.options" class="text-xs text-red-600 mt-1">{{ errors.options }}</p>
                </div>

                <div class="flex justify-end">
                    <button class="btn btn-primary">Simpan Scene</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>