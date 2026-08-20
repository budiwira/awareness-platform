<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({ stats: Object });

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Tenant Dashboard" />

    <AppLayout title="Tenant Dashboard">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <StatCard label="Total Members" :value="stats.total_users" accent="border-indigo-500" />
            <StatCard label="Active Members" :value="stats.active_users" accent="border-emerald-500" />
            <StatCard label="Admins" :value="stats.admins" accent="border-amber-500" />
        </div>

        <div class="mt-8 bg-white rounded-xl shadow-sm p-6">
            <div class="font-semibold text-gray-800 mb-4">
                Program Awareness — {{ user?.tenant_name }}
            </div>

            <div class="space-y-4">
                <div v-for="mod in [
                    { name: 'Training & Microlearning', progress: 0 },
                    { name: 'Quiz & Assessment', progress: 0 },
                    { name: 'CTF Awareness', progress: 0 },
                    { name: 'Policy Acknowledgment', progress: 0 },
                ]" :key="mod.name">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">{{ mod.name }}</span>
                        <span class="text-gray-400 text-xs uppercase tracking-wide">module ready soon</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" :style="{ width: mod.progress + '%' }"></div>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-400 mt-6">
                Data di halaman ini hanya mencakup organisasi Anda. Isolasi lintas tenant dijaga di level aplikasi dan database.
            </p>
        </div>
    </AppLayout>
</template>