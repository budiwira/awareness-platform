<script setup>
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

const hasAar = computed(() => {
    return props.exercise.aar_notes || (props.exercise.corrective_actions && props.exercise.corrective_actions.length > 0);
});
</script>

<template>
    <Head :title="exercise.title" />

    <AppLayout :title="exercise.title">
        <div class="mb-6">
            <Link :href="route('tenant.ttx.exercises.index')" class="text-sm hover:underline" style="color: var(--brand);">
                ← Kembali ke Daftar Exercise
            </Link>
        </div>

        <!-- HERO -->
        <div class="card mb-6 overflow-hidden">
            <div class="p-8" style="background: linear-gradient(135deg, var(--brand) 0%, var(--brand-strong) 100%);">
                <div class="flex items-start justify-between mb-3">
                    <h1 class="font-display text-3xl font-bold text-white">{{ exercise.title }}</h1>
                    <span class="badge" style="background: rgb(251 191 36); color: rgb(120 53 15);">
                        {{ phases[exercise.phase] }}
                    </span>
                </div>
                <p class="text-sm text-white/80 mb-4">
                    Dijadwalkan: {{ exercise.scheduled_at ? new Date(exercise.scheduled_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '—' }}
                </p>
                <p class="text-white/95 leading-relaxed">
                    {{ exercise.scenario || 'Belum ada skenario.' }}
                </p>
            </div>
        </div>

        <!-- STEPPER 5 FASE -->
        <div class="card mb-6 p-6">
            <div class="flex items-center justify-between">
                <div v-for="(key, i) in phaseKeys" :key="key" class="flex flex-col items-center relative" style="flex: 1;">
                    <!-- Garis penghubung kiri -->
                    <div v-if="i > 0" class="absolute top-5 h-0.5 -left-1/2 right-1/2 -translate-y-1/2"
                        :style="{ background: i <= currentIndex ? 'var(--brand)' : 'var(--line)' }"></div>
                    
                    <!-- Lingkaran -->
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm relative z-10"
                        :style="exercise.phase === 'completed' ? 'background: var(--brand); color: var(--white);' : (i < currentIndex ? 'background: var(--brand); color: var(--white);' : (i === currentIndex ? 'background: var(--white); border: 2px solid rgb(251 191 36); color: rgb(251 191 36);' : 'background: var(--white); border: 1px solid var(--line); color: var(--muted);'))">
                        <template v-if="exercise.phase === 'completed' || i < currentIndex">✓</template>
                        <template v-else>{{ i + 1 }}</template>
                    </div>
                    
                    <!-- Label -->
                    <div class="text-xs mt-2 text-center font-medium"
                        :style="{ color: i === currentIndex ? 'rgb(251 191 36)' : 'var(--muted)' }">
                        {{ phases[key] }}
                    </div>
                </div>
            </div>
        </div>

        <!-- GRID 2 KOLOM -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- KIRI -->
            <div class="space-y-6">
                <!-- Objectives & Scope -->
                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold mb-4" style="color: var(--ink);">Objectives & Scope</h2>
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-sm font-semibold mb-1" style="color: var(--muted);">Tujuan</h3>
                            <p class="text-sm" style="color: var(--ink);">{{ exercise.objectives || '—' }}</p>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold mb-1" style="color: var(--muted);">Scope</h3>
                            <p class="text-sm" style="color: var(--ink);">{{ exercise.scope || '—' }}</p>
                        </div>
                    </div>
                </div>

                <!-- AAR & Corrective Actions -->
                <div v-if="hasAar" class="card p-6">
                    <h2 class="font-display text-lg font-bold mb-4" style="color: var(--ink);">AAR & Corrective Actions</h2>
                    <div class="space-y-3">
                        <div v-if="exercise.aar_notes">
                            <h3 class="text-sm font-semibold mb-1" style="color: var(--muted);">AAR Notes</h3>
                            <p class="text-sm" style="color: var(--ink);">{{ exercise.aar_notes }}</p>
                        </div>
                        <div v-if="exercise.corrective_actions && exercise.corrective_actions.length > 0">
                            <h3 class="text-sm font-semibold mb-2" style="color: var(--muted);">Corrective Actions</h3>
                            <ul class="space-y-1">
                                <li v-for="(action, idx) in exercise.corrective_actions" :key="idx" class="text-sm flex items-start gap-2">
                                    <span style="color: rgb(251 191 36); margin-top: 2px;">•</span>
                                    <span style="color: var(--ink);">{{ action }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KANAN: Tim -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-display text-lg font-bold" style="color: var(--ink);">Tim</h2>
                    <button @click="showTeamForm = !showTeamForm" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                        + Tim
                    </button>
                </div>

                <!-- Form tambah tim -->
                <div v-if="showTeamForm" class="mb-4 p-4 rounded-lg" style="background: var(--surface);">
                    <form @submit.prevent="submitTeam" class="space-y-3">
                        <input v-model="teamForm.name" type="text" required placeholder="Nama tim" class="input" />
                        <input v-model="teamForm.description" type="text" placeholder="Deskripsi (opsional)" class="input" />
                        <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Simpan</button>
                    </form>
                </div>

                <!-- Daftar tim -->
                <div class="space-y-3">
                    <div v-for="team in exercise.teams" :key="team.id" class="card p-4">
                        <h3 class="font-semibold mb-1" style="color: var(--ink);">{{ team.name }}</h3>
                        <p v-if="team.description" class="text-xs mb-3" style="color: var(--muted);">{{ team.description }}</p>
                        
                        <!-- Chips anggota -->
                        <div class="flex flex-wrap gap-2 mb-3">
                            <div v-for="m in team.members" :key="m.id" 
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
                                style="background: var(--brand-soft); color: var(--brand-strong);">
                                <span>{{ m.user?.name }}</span>
                                <span class="opacity-60">•</span>
                                <span class="text-xs">{{ m.role_in_team }}</span>
                            </div>
                            <span v-if="team.members.length === 0" class="text-xs" style="color: var(--muted);">Belum ada anggota</span>
                        </div>

                        <!-- Form tambah anggota -->
                        <form @submit.prevent="submitMember(team.id)" class="flex gap-2">
                            <select v-model="memberForm(team.id).user_id" class="input flex-1" style="font-size: 0.8rem; padding: 0.4rem 0.6rem;">
                                <option value="">-- Pilih user --</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                            </select>
                            <button type="submit" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">+ Anggota</button>
                        </form>
                    </div>
                    <p v-if="exercise.teams.length === 0" class="text-center text-sm py-4" style="color: var(--muted);">
                        Belum ada tim.
                    </p>
                </div>
            </div>
        </div>

        <!-- INJECTS: Timeline vertikal -->
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-display text-lg font-bold" style="color: var(--ink);">Injects</h2>
                <button @click="showInjectForm = !showInjectForm" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    + Inject
                </button>
            </div>

            <!-- Form tambah inject -->
            <div v-if="showInjectForm" class="mb-6 p-4 rounded-lg" style="background: var(--surface);">
                <form @submit.prevent="submitInject" class="space-y-3">
                    <input v-model="injectForm.title" type="text" required placeholder="Judul inject (mis. 09:30 — Eskalasi)" class="input" />
                    <input v-model="injectForm.description" type="text" placeholder="Deskripsi komplikasi" class="input" />
                    <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Simpan</button>
                </form>
            </div>

            <!-- Timeline -->
            <div v-if="exercise.injects && exercise.injects.length > 0" class="relative pl-6">
                <!-- Garis vertikal -->
                <div class="absolute left-2 top-0 bottom-0 w-0.5" style="background: var(--line);"></div>
                
                <div v-for="(inj, idx) in exercise.injects" :key="inj.id" class="relative mb-4 last:mb-0">
                    <!-- Titik amber -->
                    <div class="absolute -left-4 top-1 w-3 h-3 rounded-full" style="background: rgb(251 191 36);"></div>
                    
                    <div>
                        <h3 class="font-semibold text-sm mb-1" style="color: var(--ink);">{{ inj.title }}</h3>
                        <p class="text-sm mb-1" style="color: var(--muted);">{{ inj.description || '—' }}</p>
                        <p class="text-xs" style="color: var(--muted); opacity: 0.7;">
                            {{ inj.created_at ? new Date(inj.created_at).toLocaleString('id-ID') : '' }}
                        </p>
                    </div>
                </div>
            </div>
            <p v-else class="text-center text-sm py-4" style="color: var(--muted);">
                Belum ada injects.
            </p>
        </div>

        <!-- AKSI FASILITATOR -->
        <div class="flex justify-end gap-3">
            <Link :href="route('tenant.ttx.exercises.evaluate', exercise.id)" class="btn btn-secondary">
                Isi Evaluasi
            </Link>
            <button v-if="exercise.phase !== 'completed'" @click="router.post(route('tenant.ttx.exercises.advance', exercise.id))" class="btn btn-primary">
                Advance Fase →
            </button>
        </div>
    </AppLayout>
</template>
