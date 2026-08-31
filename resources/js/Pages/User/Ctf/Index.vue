<script setup>
import { computed, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ challenges: Array });

const errors = computed(() => usePage().props.errors ?? {});

const forms = reactive({});

const submit = (id) => {
    router.post(route('user.ctf.submit', id), { flag: forms[id] ?? '' }, {
        preserveScroll: true,
        onSuccess: () => (forms[id] = ''),
    });
};

const difficultyBadge = (d) => ({
    beginner: 'bg-emerald-100 text-emerald-700',
    intermediate: 'bg-yellow-100 text-yellow-700',
    advanced: 'bg-red-100 text-red-700',
}[d] ?? 'bg-gray-100 text-gray-700');
</script>

<template>
    <Head title="CTF" />

    <AppLayout title="Capture The Flag">
        <p class="text-sm text-gray-500 mb-6">
            Pecahkan tantangan keamanan dan kumpulkan poin. Flag diverifikasi di server.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="c in challenges" :key="c.id" class="card p-6 flex flex-col">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900">{{ c.title }}</h3>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="difficultyBadge(c.difficulty)">
                        {{ c.difficulty }}
                    </span>
                </div>

                <p class="text-sm text-gray-500 mb-2 flex-1">{{ c.description }}</p>

                <div class="text-xs text-gray-400 mb-4">
                    {{ c.category }} · {{ c.points }} poin
                    <span v-if="c.hint" class="block mt-1 text-amber-600">Hint: {{ c.hint }}</span>
                </div>

                <div v-if="c.solved" class="flex items-center gap-2 text-emerald-600 text-sm font-medium">
                    ✓ Solved · +{{ c.solved_points }} poin
                </div>

                <form v-else @submit.prevent="submit(c.id)" class="flex gap-2">
                    <input
                        v-model="forms[c.id]"
                        type="text"
                        required
                        placeholder="FLAG{...}"
                        class="input flex-1 font-mono"
                    />
                    <button class="btn btn-primary">Submit</button>
                </form>
                <p v-if="errors.flag" class="text-xs text-red-600 mt-2">{{ errors.flag }}</p>
            </div>

            <div v-if="challenges.length === 0" class="col-span-2 card p-8 text-center text-gray-500 text-sm">
                Belum ada challenge aktif.
            </div>
        </div>
    </AppLayout>
</template>