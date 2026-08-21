<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex">
        <!-- Panel branding (kiri) -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-900 text-white flex-col justify-between p-12">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-9 w-9 text-indigo-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l8 4v6c0 5.25-3.4 9.74-8 10-4.6-.26-8-4.75-8-10V6l8-4z" />
                </svg>
                <span class="font-bold text-lg">Awareness Platform</span>
            </div>

            <div>
                <h1 class="text-4xl font-bold leading-tight">
                    Bangun budaya<br />keamanan digital<br />organisasi Anda.
                </h1>
                <p class="mt-4 text-indigo-200 max-w-md text-sm leading-relaxed">
                    Training, quiz, CTF awareness, dan pengukuran risiko manusia
                    dalam satu platform yang aman dan terisolasi per organisasi.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-indigo-100">
                    <li class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-xs">✓</span>
                        Isolasi data antar organisasi (defense-in-depth)
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-xs">✓</span>
                        Awareness score yang explainable, bukan black-box
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-xs">✓</span>
                        Simulasi insiden & challenge yang aman untuk MVP
                    </li>
                </ul>
            </div>

            <p class="text-xs text-indigo-300">© 2025 Cyber Security Awareness Platform</p>
        </div>

        <!-- Panel form (kanan) -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gray-50">
            <div class="w-full max-w-md">
                <div class="lg:hidden flex items-center justify-center gap-2 mb-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2l8 4v6c0 5.25-3.4 9.74-8 10-4.6-.26-8-4.75-8-10V6l8-4z" />
                    </svg>
                    <span class="font-bold text-gray-900">Awareness Platform</span>
                </div>

                <h2 class="text-2xl font-bold text-gray-900">Selamat datang 👋</h2>
                <p class="text-sm text-gray-500 mt-1">Masuk ke akun organisasi Anda.</p>

                <div v-if="status" class="mt-4 text-sm font-medium text-green-600">{{ status }}</div>

                <form class="mt-8 space-y-6" @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                v-model="form.remember"
                            />
                            <span class="text-sm text-gray-600">Ingat saya</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-indigo-600 hover:underline"
                        >
                            Lupa password?
                        </Link>
                    </div>
<noscript>
    <div style="background:red;color:white;padding:10px;margin:10px 0">
        JavaScript dimatikan! Form akan submit biasa via HTML.
    </div>
</noscript>
                    <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing">
                        Masuk
                    </PrimaryButton>
                </form>

                <!-- PANEL AKUN DEMO — HAPUS SEBELUM PRODUCTION -->
                <div class="mt-8 bg-indigo-50 border border-indigo-100 rounded-lg p-4 text-xs text-indigo-700 leading-relaxed">
                    <div class="font-semibold mb-1">Akun demo:</div>
                    superadmin@platform.local / password<br />
                    admin@acme.local / password<br />
                    user@acme.local / password
                </div>
            </div>
        </div>
    </div>
    <!-- DEBUG: lihat apakah form benar-benar submit tanpa JS -->
<noscript>
    <div style="background:red;color:white;padding:10px;margin:10px 0">
        JavaScript dimatikan! Form akan submit biasa via HTML.
    </div>
</noscript>
</template>