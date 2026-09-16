<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseBadge from '@/Components/BaseBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseInput from '@/Components/BaseInput.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({ challenges: Array });
const errors = computed(() => usePage().props.errors ?? {});
const forms = reactive({});
const processing = ref(null);
const errorChallenge = ref(null);

const submit = (id) => {
    if (processing.value) return;
    processing.value = id;
    errorChallenge.value = id;
    router.post(route('user.ctf.submit', id), { flag: forms[id] ?? '' }, {
        preserveScroll: true,
        onSuccess: () => {
            forms[id] = '';
            errorChallenge.value = null;
        },
        onFinish: () => (processing.value = null),
    });
};

const difficultyBadge = (difficulty) => ({ beginner: 'success', intermediate: 'warning', advanced: 'danger' }[difficulty] ?? 'neutral');
</script>

<template>
    <Head title="CTF" />
    <AppLayout title="Capture The Flag">
        <p class="mb-6 text-sm t-muted">Pecahkan tantangan keamanan dan kumpulkan poin. Flag diverifikasi di server.</p>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <article v-for="challenge in challenges" :key="challenge.id" class="card flex flex-col p-5 sm:p-6">
                <div class="mb-2 flex items-start justify-between gap-3">
                    <h3 class="font-semibold t-ink">{{ challenge.title }}</h3>
                    <BaseBadge :variant="difficultyBadge(challenge.difficulty)">{{ challenge.difficulty }}</BaseBadge>
                </div>
                <p class="mb-2 flex-1 text-sm t-muted">{{ challenge.description }}</p>
                <div class="mb-4 text-xs t-muted">
                    {{ challenge.category }} · {{ challenge.points }} poin
                    <div v-if="challenge.hint" class="mt-2"><BaseBadge variant="warning">Hint: {{ challenge.hint }}</BaseBadge></div>
                </div>
                <div v-if="challenge.solved" class="flex items-center gap-2 text-sm font-medium" style="color: var(--ok)">
                    <svg class="h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    Selesai · +{{ challenge.solved_points }} poin
                </div>
                <form v-else class="flex flex-col gap-2 sm:flex-row sm:items-start" @submit.prevent="submit(challenge.id)">
                    <BaseInput v-model="forms[challenge.id]" required placeholder="FLAG{...}" class="flex-1" :error="errorChallenge === challenge.id ? errors.flag : undefined" :disabled="processing !== null" />
                    <BaseButton type="submit" :loading="processing === challenge.id" :disabled="processing !== null && processing !== challenge.id">{{ processing === challenge.id ? 'Mengirim...' : 'Submit' }}</BaseButton>
                </form>
            </article>
            <div v-if="challenges.length === 0" class="card md:col-span-2"><EmptyState title="Belum ada challenge" message="Challenge aktif akan muncul di halaman ini." /></div>
        </div>
    </AppLayout>
</template>
