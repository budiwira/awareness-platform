<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ challenges: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({
    title: '',
    description: '',
    category: 'general',
    difficulty: 'beginner',
    points: 100,
    flag: '',
    hint: '',
});

const submit = () => {
    router.post(route('platform.ctf.store'), form.value, {
        onSuccess: () => (showForm.value = false),
    });
};

const difficultyBadge = (d) => ({
    beginner: 'bg-emerald-100 text-emerald-700',
    intermediate: 'bg-yellow-100 text-yellow-700',
    advanced: 'bg-red-100 text-red-700',
}[d] ?? 'bg-gray-100 text-gray-700');
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
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500"
            >
                + Buat Challenge
            </button>
        </div>

        <div v-if="showForm" class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Judul</label>
                    <input v-model="form.title" type="text" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                    <p v-if="errors.title" class="text-xs text-red-600 mt-1">{{ errors.title }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Deskripsi Tantangan</label>
                    <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Kategori</label>
                    <select v-model="form.category" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="general">General</option>
                        <option value="phishing">Phishing</option>
                        <option value="password">Password</option>
                        <option value="social_engineering">Social Engineering</option>
                        <option value="web">Web</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Kesulitan</label>
                    <select v-model="form.difficulty" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Poin</label>
                    <input v-model.number="form.points" type="number" min="10" max="1000" required class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Flag (rahasia)</label>
                    <input v-model="form.flag" type="text" required placeholder="FLAG{contoh_flag}" class="mt-1 w-full rounded-lg border-gray-300 text-sm font-mono" />
                    <p v-if="errors.flag" class="text-xs text-red-600 mt-1">{{ errors.flag }}</p>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-gray-600">Hint (opsional)</label>
                    <input v-model="form.hint" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Judul</th>
                        <th class="px-6 py-3 font-medium">Kategori</th>
                        <th class="px-6 py-3 font-medium">Kesulitan</th>
                        <th class="px-6 py-3 font-medium">Poin</th>
                        <th class="px-6 py-3 font-medium">Solved</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in challenges" :key="c.id" class="border-b border-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ c.title }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ c.category }}</td>
                        <td class="px-6 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="difficultyBadge(c.difficulty)">
                                {{ c.difficulty }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ c.points }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ c.solves_count }}</td>
                    </tr>
                    <tr v-if="challenges.length === 0">
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Belum ada challenge.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>