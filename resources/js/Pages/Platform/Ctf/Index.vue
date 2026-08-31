<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ challenges: Array });

const errors = computed(() => usePage().props.errors ?? {});

const statusFilter = ref('all');

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
    router.post(route('platform.ctf.store'), form.value, {
        onSuccess: () => (showForm.value = false),
    });
};

const publish = (id) => {
    router.post(route('platform.ctf.publish', id));
};

const archive = (id) => {
    if (confirm('Arsipkan challenge ini?')) {
        router.post(route('platform.ctf.archive', id));
    }
};

const difficultyBadge = (d) => ({
    beginner: 'bg-emerald-100 text-emerald-700',
    intermediate: 'bg-yellow-100 text-yellow-700',
    advanced: 'bg-red-100 text-red-700',
}[d] ?? 'bg-gray-100 text-gray-700');

const statusBadge = (status) => {
    const map = {
        draft: 'bg-gray-100 text-gray-700',
        published: 'bg-emerald-100 text-emerald-700',
        archived: 'bg-amber-100 text-amber-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
};

const statusLabel = (status) => {
    const map = { draft: 'Draft', published: 'Published', archived: 'Archived' };
    return map[status] || status;
};
</script>

<template>
    <Head title="CTF Challenges" />

    <AppLayout title="CTF Challenge Builder">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">
                Buat challenge gamifikasi. Flag diverifikasi server-side dan tidak pernah dikirim ke browser.
            </p>
            <button
                @click="showForm = !showForm"
                class="btn btn-primary"
            >
                + Buat Challenge
            </button>
        </div>

        <div v-if="showForm" class="card p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Judul</label>
                    <input v-model="form.title" type="text" required class="input mt-1 w-full" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Deskripsi Tantangan</label>
                    <textarea v-model="form.description" rows="2" class="input mt-1 w-full"></textarea>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Kategori</label>
                    <select v-model="form.category" class="input mt-1 w-full">
                        <option value="general">General</option>
                        <option value="phishing">Phishing</option>
                        <option value="password">Password</option>
                        <option value="social_engineering">Social Engineering</option>
                        <option value="web">Web</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Kesulitan</label>
                    <select v-model="form.difficulty" class="input mt-1 w-full">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Poin</label>
                    <input v-model.number="form.points" type="number" min="10" max="1000" required class="input mt-1 w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Flag (rahasia)</label>
                    <input v-model="form.flag" type="text" required placeholder="FLAG{contoh_flag}" class="input mt-1 w-full font-mono" />
                    <p v-if="errors.flag" class="text-xs text-red-600 mt-1">{{ errors.flag }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Hint (opsional)</label>
                    <input v-model="form.hint" type="text" class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
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
                :class="statusFilter === key ? 'bg-teal-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'"
            >
                {{ key === 'all' ? 'Semua' : statusLabel(key) }} <span class="opacity-75">({{ count }})</span>
            </button>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
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
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <div class="text-sm">Tidak ada challenge dengan status ini.</div>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="c in filteredChallenges" :key="c.id" class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ c.title }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ c.category }}</td>
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
                        <td class="px-6 py-3 text-gray-500">{{ c.points }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ c.solves_count }}</td>
                        <td class="px-6 py-3 text-right space-x-3">
                            <button v-if="c.status === 'draft'" @click="publish(c.id)" class="text-emerald-600 text-sm font-medium hover:underline">Publish</button>
                            <button v-if="c.status === 'published'" @click="archive(c.id)" class="text-amber-600 text-sm font-medium hover:underline">Archive</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
