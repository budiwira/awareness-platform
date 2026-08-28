<script setup>

const showInjectForm = ref(false);
const injectForm = ref({ title: '', description: '' });

const submitInject = () => {
    router.post(route('tenant.ttx.exercises.injects.store', props.exercise.id), injectForm.value, {
        onSuccess: () => {
            injectForm.value = { title: '', description: '' };
            showInjectForm.value = false;
        },
    });
};

import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ exercise: Object, users: Array, phases: Object });

const errors = computed(() => usePage().props.errors ?? {});

const phaseKeys = ['planning', 'preparation', 'execution', 'evaluation', 'completed'];
const currentIndex = phaseKeys.indexOf(props.exercise.phase);

const showTeamForm = ref(false);
const teamForm = ref({ name: '', description: '' });

const memberForms = ref({});

const memberForm = (teamId) => {
    if (!memberForms.value[teamId]) {
        memberForms.value[teamId] = { user_id: '', role_in_team: 'member' };
    }
    return memberForms.value[teamId];
};

const submitTeam = () => {
    router.post(route('tenant.ttx.teams.store', props.exercise.id), teamForm.value, {
        onSuccess: () => {
            teamForm.value = { name: '', description: '' };
            showTeamForm.value = false;
        },
    });
};

const submitMember = (teamId) => {
    router.post(route('tenant.ttx.teams.members.store', teamId), memberForm(teamId), {
        onSuccess: () => (memberForm(teamId).user_id = ''),
    });
};
</script>

<template>
    <Head :title="exercise.title" />

    <AppLayout :title="exercise.title">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.exercises.index')" class="text-sm text-indigo-600 hover:underline">
                ← Kembali ke Daftar Exercise
            </Link>
        </div>

        <!-- PHASE STEPPER -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex items-center">
                <div v-for="(key, i) in phaseKeys" :key="key" class="flex-1 flex flex-col items-center relative">
                    <div
                        class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold z-10"
                        :class="i < currentIndex ? 'bg-emerald-500 text-white' : (i === currentIndex ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500')"
                    >
                        {{ i + 1 }}
                    </div>
                    <div class="text-xs mt-2 text-center" :class="i === currentIndex ? 'text-indigo-600 font-semibold' : 'text-gray-500'">
                        {{ phases[key] }}
                    </div>
                    <div v-if="i < phaseKeys.length - 1"
                        class="absolute top-4 left-1/2 w-full h-0.5"
                        :class="i < currentIndex ? 'bg-emerald-500' : 'bg-gray-200'"></div>
                </div>
            </div>
        </div>
                <!-- AKSI FASILITATOR -->
        <div class="flex justify-end gap-2 mb-6">
            <Link :href="route('tenant.ttx.exercises.evaluate', exercise.id)"
                class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-500">
                Isi Evaluasi / AAR
            </Link>
            <button v-if="exercise.phase !== 'completed'" @click="router.post(route('tenant.ttx.exercises.advance', exercise.id))"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">
                Advance Fase →
            </button>
        </div>

        <!-- INFO -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-2">{{ exercise.title }}</h2>
            <p class="text-sm text-gray-600 mb-2"><span class="font-semibold">Skenario:</span> {{ exercise.scenario }}</p>
            <p class="text-sm text-gray-600 mb-2"><span class="font-semibold">Tujuan:</span> {{ exercise.objectives }}</p>
            <p class="text-xs text-gray-400">Playbook: {{ exercise.playbook?.title ?? '—' }} · Runbook: {{ exercise.runbook?.title ?? '—' }}</p>
        </div>

        <!-- INJECTS (preview) -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="font-semibold text-gray-800 mb-3">Injects ({{ exercise.injects?.length ?? 0 }})</h3>
                        <div class="mb-3">
                <button @click="showInjectForm = !showInjectForm"
                    class="px-3 py-1.5 rounded-lg bg-yellow-500 text-white text-sm hover:bg-yellow-400">+ Inject</button>
            </div>

            <form v-if="showInjectForm" @submit.prevent="submitInject" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <input v-model="injectForm.title" type="text" required placeholder="Judul inject (mis. 09:30 — Eskalasi)"
                    class="rounded-lg border-gray-300 text-sm" />
                <input v-model="injectForm.description" type="text" placeholder="Deskripsi komplikasi"
                    class="rounded-lg border-gray-300 text-sm" />
                <button class="px-4 py-2 rounded-lg bg-yellow-500 text-white text-sm">Simpan</button>
            </form>
            <ol class="space-y-2">
                <li v-for="inj in exercise.injects" :key="inj.id" class="text-sm border-l-4 border-yellow-400 bg-yellow-50 rounded-r-lg p-3">
                    <span class="font-medium">{{ inj.title }}</span> — {{ inj.description }}
                </li>
            </ol>
            <p v-if="(exercise.injects?.length ?? 0) === 0" class="text-sm text-gray-500">Belum ada injects.</p>
        </div>

        <!-- TEAMS -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-800">Tim & Anggota</h3>
                <button @click="showTeamForm = !showTeamForm"
                    class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">+ Tim</button>
            </div>

            <div v-if="showTeamForm" class="mb-4 p-4 bg-gray-50 rounded-lg">
                <form @submit.prevent="submitTeam" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <input v-model="teamForm.name" type="text" required placeholder="Nama tim (mis. Tim Recovery)"
                        class="rounded-lg border-gray-300 text-sm" />
                    <input v-model="teamForm.description" type="text" placeholder="Deskripsi" class="rounded-lg border-gray-300 text-sm" />
                    <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="team in exercise.teams" :key="team.id" class="border border-gray-100 rounded-xl p-4">
                    <h4 class="font-medium text-gray-900 mb-1">{{ team.name }}</h4>
                    <p class="text-xs text-gray-500 mb-3">{{ team.description }}</p>

                    <ul class="space-y-1 mb-3">
                        <li v-for="m in team.members" :key="m.id" class="text-sm flex items-center justify-between">
                            <span>{{ m.user?.name }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full"
                                :class="m.role_in_team === 'lead' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'">
                                {{ m.role_in_team }}
                            </span>
                        </li>
                        <li v-if="team.members.length === 0" class="text-xs text-gray-400">Belum ada anggota.</li>
                    </ul>

                    <form @submit.prevent="submitMember(team.id)" class="flex gap-2">
                        <select v-model="memberForm(team.id).user_id" class="flex-1 rounded-lg border-gray-300 text-sm">
                            <option value="">-- Pilih user --</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm">+ Anggota</button>
                    </form>
                </div>
                <div v-if="exercise.teams.length === 0" class="col-span-2 text-center text-gray-500 text-sm py-6">
                    Belum ada tim.
                </div>
            </div>
        </div>
    </AppLayout>
</template>