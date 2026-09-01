<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ module: Object });

const errors = computed(() => usePage().props.errors ?? {});
const isEdit = computed(() => props.module !== null);

const step = ref(1);
const form = ref({
    title: props.module?.title || '',
    description: props.module?.description || '',
    duration_minutes: props.module?.duration_minutes || 15,
    content: props.module?.content || '',
    status: props.module?.status || 'draft',
    is_active: props.module?.is_active ?? true,
});

const contentBlocks = ref(
    props.module?.content
        ? props.module.content.split('\n\n').filter(b => b.trim()).map((text, i) => ({ id: Date.now() + i, text }))
        : [{ id: Date.now(), text: '' }]
);

const steps = [
    { num: 1, label: 'Info Dasar' },
    { num: 2, label: 'Materi' },
    { num: 3, label: 'Evaluasi' },
    { num: 4, label: 'Review & Publish' },
];

const canNext = computed(() => {
    if (step.value === 1) return form.value.title && form.value.duration_minutes > 0;
    if (step.value === 2) return contentBlocks.value.some(b => b.text.trim());
    return true;
});

const addBlock = () => {
    contentBlocks.value.push({ id: Date.now(), text: '' });
};

const removeBlock = (id) => {
    if (contentBlocks.value.length > 1) {
        contentBlocks.value = contentBlocks.value.filter(b => b.id !== id);
    }
};

const moveUp = (index) => {
    if (index > 0) {
        const arr = [...contentBlocks.value];
        [arr[index - 1], arr[index]] = [arr[index], arr[index - 1]];
        contentBlocks.value = arr;
    }
};

const moveDown = (index) => {
    if (index < contentBlocks.value.length - 1) {
        const arr = [...contentBlocks.value];
        [arr[index], arr[index + 1]] = [arr[index + 1], arr[index]];
        contentBlocks.value = arr;
    }
};

const next = () => {
    if (canNext.value && step.value < 4) step.value++;
};

const prev = () => {
    if (step.value > 1) step.value--;
};

const submit = () => {
    form.value.content = contentBlocks.value.map(b => b.text.trim()).filter(t => t).join('\n\n');
    
    if (isEdit.value) {
        router.patch(route('platform.modules.update', props.module.id), form.value);
    } else {
        router.post(route('platform.modules.store'), form.value);
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Modul' : 'Buat Modul Baru'" />

    <AppLayout :title="isEdit ? 'Edit Modul' : 'Buat Modul Baru'">
        <!-- Progress stepper -->
        <div class="flex items-center justify-center gap-2 mb-8">
            <div v-for="s in steps" :key="s.num" class="flex items-center gap-2">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold transition-all"
                    :class="step >= s.num ? 'chip-active' : 'bg-surface2 t-muted'"
                >
                    {{ s.num }}
                </div>
                <span class="text-sm font-medium" :class="step >= s.num ? 't-ink' : 'text-gray-400'">{{ s.label }}</span>
                <svg v-if="s.num < 4" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>

        <div class="card p-8 max-w-4xl mx-auto">
            <!-- Step 1: Info Dasar -->
            <div v-if="step === 1" class="space-y-4 fade-in">
                <div>
                    <label class="text-sm font-medium t-ink">Judul Modul</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" placeholder="Contoh: Phishing Awareness Fundamentals" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium t-ink">Kategori</label>
                        <select class="input mt-1 w-full">
                            <option>General Security</option>
                            <option>Phishing</option>
                            <option>Password</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium t-ink">Durasi (menit)</label>
                        <input v-model.number="form.duration_minutes" type="number" min="1" required class="input mt-1 w-full" />
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium t-ink">Deskripsi Singkat</label>
                    <textarea v-model="form.description" rows="3" class="input mt-1 w-full" placeholder="Ringkasan untuk katalog..."></textarea>
                </div>
            </div>

            <!-- Step 2: Materi -->
            <div v-if="step === 2" class="space-y-4 fade-in">
                <p class="text-sm t-muted mb-4">Susun blok konten secara berurutan. Gunakan tombol untuk menambah, hapus, atau menggeser urutan.</p>
                <div v-for="(block, i) in contentBlocks" :key="block.id" class="border b-line rounded-lg p-4 bg-app space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold t-muted">Blok {{ i + 1 }}</span>
                        <div class="flex gap-1">
                            <button @click="moveUp(i)" :disabled="i === 0" class="p-1 t-muted hover:t-ink disabled:opacity-30" title="Naik">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                </svg>
                            </button>
                            <button @click="moveDown(i)" :disabled="i === contentBlocks.length - 1" class="p-1 t-muted hover:t-ink disabled:opacity-30" title="Turun">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <button @click="removeBlock(block.id)" :disabled="contentBlocks.length === 1" class="p-1 text-red-500 hover:text-red-700 disabled:opacity-30" title="Hapus">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <textarea v-model="block.text" rows="4" class="input w-full" placeholder="Masukkan teks, HTML, atau markdown..."></textarea>
                </div>
                <button @click="addBlock" class="btn btn-secondary w-full">+ Tambah Blok Konten</button>
            </div>

            <!-- Step 3: Evaluasi -->
            <div v-if="step === 3" class="space-y-4 fade-in">
                <p class="text-sm t-muted">
                    Kuis untuk modul ini dikelola di halaman <strong>Quizzes</strong>. Passing score bisa diatur di sana.
                </p>
                <div class="badge-warn  rounded-lg p-4 text-sm badge-warn">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Setelah modul dibuat, buka halaman <strong>Detail Modul</strong> dan klik <strong>"Kelola Kuis"</strong> untuk menambah pertanyaan.
                </div>
            </div>

            <!-- Step 4: Review & Publish -->
            <div v-if="step === 4" class="space-y-6 fade-in">
                <div class="bg-app border b-line rounded-lg p-6">
                    <div class="font-display text-xl font-bold t-ink mb-2">{{ form.title }}</div>
                    <div class="text-sm t-muted mb-4">{{ form.description }}</div>
                    <div class="flex items-center gap-4 text-xs t-muted">
                        <span>⏱ {{ form.duration_minutes }} menit</span>
                        <span>📄 {{ contentBlocks.filter(b => b.text.trim()).length }} blok konten</span>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium t-ink">Status Publikasi</label>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.status" value="draft" class="chip-brand" />
                            <div>
                                <div class="text-sm font-medium t-ink">Draft</div>
                                <div class="text-xs t-muted">Simpan sebagai draft, tidak terlihat tenant.</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="form.status" value="published" class="chip-brand" />
                            <div>
                                <div class="text-sm font-medium t-ink">Published</div>
                                <div class="text-xs t-muted">Publikasikan, tenant bisa menugaskan ke user.</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-8 pt-6 border-t b-line">
                <button v-if="step > 1" @click="prev" class="btn btn-secondary">← Sebelumnya</button>
                <div v-else></div>
                <button v-if="step < 4" @click="next" :disabled="!canNext" class="btn btn-primary">Lanjut →</button>
                <button v-else @click="submit" class="btn btn-primary">{{ isEdit ? 'Simpan Perubahan' : 'Buat Modul' }}</button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-in {
    animation: fadeIn 0.2s ease-in;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
