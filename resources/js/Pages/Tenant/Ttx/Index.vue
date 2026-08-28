<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
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
                        class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-500">
                        + Playbook
                    </button>
                </div>

                <div v-if="showPlaybookForm" class="bg-white rounded-xl shadow-sm p-5 mb-4">
                    <form @submit.prevent="submitPlaybook" class="space-y-3">
                        <input v-model="playbookForm.title" type="text" required placeholder="Judul playbook"
                            class="w-full rounded-lg border-gray-300 text-sm" />
                        <textarea v-model="playbookForm.description" rows="2" placeholder="Deskripsi singkat"
                            class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        <textarea v-model="playbookForm.content" rows="4" placeholder="Prosedur (satu langkah per baris)"
                            class="w-full rounded-lg border-gray-300 text-sm font-mono"></textarea>
                        <button class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm">Simpan</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <div v-for="pb in playbooks" :key="pb.id" class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-indigo-500">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900">{{ pb.title }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">aktif</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ pb.description }}</p>
                    </div>
                    <div v-if="playbooks.length === 0" class="bg-white rounded-xl shadow-sm p-6 text-center text-gray-500 text-sm">
                        Belum ada playbook.
                    </div>
                </div>
            </div>

            <!-- RUNBOOKS -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-gray-800">Runbook</h2>
                    <button @click="showRunbookForm = !showRunbookForm"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-500">
                        + Runbook
                    </button>
                </div>

                <div v-if="showRunbookForm" class="bg-white rounded-xl shadow-sm p-5 mb-4">
                    <form @submit.prevent="submitRunbook" class="space-y-3">
                        <input v-model="runbookForm.title" type="text" required placeholder="Judul runbook"
                            class="w-full rounded-lg border-gray-300 text-sm" />
                        <textarea v-model="runbookForm.description" rows="2" placeholder="Deskripsi singkat"
                            class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        <textarea v-model="runbookForm.steps" rows="4" placeholder="Langkah teknis (satu per baris)"
                            class="w-full rounded-lg border-gray-300 text-sm font-mono"></textarea>
                        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm">Simpan</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <div v-for="rb in runbooks" :key="rb.id" class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-emerald-500">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-gray-900">{{ rb.title }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">aktif</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">{{ rb.description }}</p>
                        <ol class="mt-2 space-y-1 text-sm text-gray-600 list-decimal list-inside">
                            <li v-for="(step, i) in rb.steps" :key="i">{{ step }}</li>
                        </ol>
                    </div>
                    <div v-if="runbooks.length === 0" class="bg-white rounded-xl shadow-sm p-6 text-center text-gray-500 text-sm">
                        Belum ada runbook.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>