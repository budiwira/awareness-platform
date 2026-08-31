<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ cases: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showForm = ref(false);
const form = ref({ title: '', description: '', difficulty: 'beginner', duration_minutes: 15 });

const submit = () => {
    router.post(route('platform.cases.store'), form.value, {
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
    <Head title="Case Studies" />

    <AppLayout title="Case Study Builder">
        <div class="flex items-center justify-between mb-6">
            <p class="text-sm text-gray-500">
                Buat latihan tabletop berbasis skenario insiden untuk mengasah pengambilan keputusan.
            </p>
            <button
                @click="showForm = !showForm"
                class="btn btn-primary"
            >
                + Buat Case Study
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
                    <label class="text-sm text-gray-600">Ringkasan Skenario</label>
                    <textarea v-model="form.description" rows="2" class="input mt-1 w-full"></textarea>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Tingkat Kesulitan</label>
                    <select v-model="form.difficulty" class="input mt-1 w-full">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600">Durasi (menit)</label>
                    <input v-model.number="form.duration_minutes" type="number" min="1" required class="input mt-1 w-full" />
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">Judul</th>
                        <th class="px-6 py-3 font-medium">Kesulitan</th>
                        <th class="px-6 py-3 font-medium">Scene</th>
                        <th class="px-6 py-3 font-medium">Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in cases" :key="c.id" class="border-b border-gray-50">
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
                        <td class="px-6 py-3 text-gray-500">{{ c.scenes_count }}</td>
                        <td class="px-6 py-3 text-gray-500">{{ c.duration_minutes }} menit</td>
                    </tr>
                    <tr v-if="cases.length === 0">
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Belum ada case study.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>