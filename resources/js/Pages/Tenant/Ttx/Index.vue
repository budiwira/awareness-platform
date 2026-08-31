<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ playbooks: Array, runbooks: Array });

const errors = computed(() => usePage().props.errors ?? {});

const showPlaybookForm = ref(false);
const showRunbookForm = ref(false);

const playbookForm = ref({ title: '', description: '', content: '' });
const runbookForm = ref({ title: '', description: '', steps: '' });

const submitPlaybook = () => {
    router.post(route('tenant.ttx.playbooks.store'), playbookForm.value, {
        onSuccess: () => {
            playbookForm.value = { title: '', description: '', content: '' };
            showPlaybookForm.value = false;
        },
    });
};

const submitRunbook = () => {
    router.post(route('tenant.ttx.runbooks.store'), runbookForm.value, {
        onSuccess: () => {
            runbookForm.value = { title: '', description: '', steps: '' };
            showRunbookForm.value = false;
        },
    });
};
</script>

<template>
    <Head title="Tabletop Exercise" />

    <AppLayout title="Tabletop Exercise (TTX)">
        <p class="text-sm text-gray-500 mb-6">
            Kelola playbook & runbook sebagai fondasi simulasi tabletop penanganan insiden.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- PLAYBOOKS -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-800">Playbook</h2>
                    <button @click="showPlaybookForm = !showPlaybookForm"
                        class="btn btn-primary">
                        + Playbook
                    </button>
                </div>

                <div v-if="showPlaybookForm" class="card p-5 mb-4">
                    <form @submit.prevent="submitPlaybook" class="space-y-3">
                        <input v-model="playbookForm.title" type="text" required placeholder="Judul playbook"
                            class="input w-full" />
                        <textarea v-model="playbookForm.description" rows="2" placeholder="Deskripsi singkat"
                            class="input w-full"></textarea>
                        <textarea v-model="playbookForm.content" rows="4" placeholder="Prosedur (satu langkah per baris)"
                            class="input w-full font-mono"></textarea>
                        <button class="btn btn-primary">Simpan</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <Link v-for="pb in playbooks" :key="pb.id" :href="route('tenant.ttx.playbooks.show', pb.id)" class="card p-5 border-l-4 border-indigo-500 block hover:shadow-md transition">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900">{{ pb.title }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">aktif</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ pb.description }}</p>
                    </Link>
                    <div v-if="playbooks.length === 0" class="card p-6 text-center text-gray-500 text-sm">
                        Belum ada playbook.
                    </div>
                </div>
            </div>

            <!-- RUNBOOKS -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-800">Runbook</h2>
                    <button @click="showRunbookForm = !showRunbookForm"
                        class="btn btn-primary">
                        + Runbook
                    </button>
                </div>

                <div v-if="showRunbookForm" class="card p-5 mb-4">
                    <form @submit.prevent="submitRunbook" class="space-y-3">
                        <input v-model="runbookForm.title" type="text" required placeholder="Judul runbook"
                            class="input w-full" />
                        <textarea v-model="runbookForm.description" rows="2" placeholder="Deskripsi singkat"
                            class="input w-full"></textarea>
                        <textarea v-model="runbookForm.steps" rows="4" placeholder="Langkah teknis (satu per baris)"
                            class="input w-full font-mono"></textarea>
                        <button class="btn btn-primary">Simpan</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <Link v-for="rb in runbooks" :key="rb.id" :href="route('tenant.ttx.runbooks.show', rb.id)" class="card p-5 border-l-4 border-emerald-500 block hover:shadow-md transition">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900">{{ rb.title }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">aktif</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ rb.description }}</p>
                        <ol class="mt-2 space-y-1 text-sm text-gray-600 list-decimal list-inside">
                            <li v-for="(step, i) in rb.steps" :key="i">{{ step }}</li>
                        </ol>
                    </Link>
                    <div v-if="runbooks.length === 0" class="card p-6 text-center text-gray-500 text-sm">
                        Belum ada runbook.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>