<script setup>
import { ref } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: false },
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk" />

    <div class="min-h-screen flex bg-app">
        <!-- Panel brand (desktop) -->
        <div
            class="hidden lg:flex lg:w-1/2 flex-col justify-between p-12 relative"
            style="background: linear-gradient(160deg, #170722 0%, #0A0A0E 100%)"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center font-display font-bold text-lg" style="background: rgba(124,58,237,.2); color: var(--brand-strong)">SA</div>
                <span class="font-display font-semibold text-lg t-on-hero">Security Awareness Platform</span>
            </div>

            <div class="beam absolute left-12 right-12" style="top: 50%"></div>

            <div>
                <h1 class="font-display text-4xl font-bold leading-tight mb-4 t-on-hero">
                    Bangun budaya<br />keamanan siber<br />organisasi Anda.
                </h1>
                <p class="t-on-hero-muted max-w-md">
                    Training terukur, simulasi tabletop exercise, dan awareness score yang
                    explainable — dalam satu platform multi-tenant.
                </p>

                <div class="mt-8 space-y-3 text-sm t-on-hero-muted">
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full" style="background: var(--brand-strong)"></span>
                        Training & quiz berbasis skenario dunia nyata
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full" style="background: var(--brand-strong)"></span>
                        Tabletop exercise 4 fase untuk tim respons insiden
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full" style="background: var(--brand-strong)"></span>
                        Awareness score 5 komponen yang transparan
                    </div>
                </div>
            </div>

            <p class="text-xs t-on-hero-muted">Akses internal organisasi terdaftar.</p>
        </div>

        <!-- Panel form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <div class="lg:hidden mb-8 text-center">
                    <div class="inline-flex w-12 h-12 rounded-xl text-white items-center justify-center font-display font-bold" style="background: var(--brand)">SA</div>
                    <h1 class="font-display text-xl font-bold mt-3 t-ink">Security Awareness Platform</h1>
                </div>

                <h2 class="font-display text-2xl font-bold t-ink">Selamat datang kembali</h2>
                <p class="text-sm mt-1" style="color: var(--muted)">Masuk untuk melanjutkan ke dashboard Anda.</p>

                <div v-if="status" class="mt-4 text-sm font-medium chip-brand rounded-lg px-4 py-3">
                    {{ status }}
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email" type="email" class="mt-1" v-model="form.email"
                            required autofocus autocomplete="username" placeholder="nama@perusahaan.co.id"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel for="password" value="Password" />
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm hover:underline" style="color: var(--brand)">
                                Lupa password?
                            </Link>
                        </div>
                        <div class="relative mt-1">
                            <TextInput
                                id="password" :type="showPassword ? 'text' : 'password'" class="pr-10" v-model="form.password"
                                required autocomplete="current-password" placeholder="••••••••"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 t-muted transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 rounded"
                                style="--tw-ring-color: var(--brand)"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                @mouseenter="$event.currentTarget.style.color = 'var(--ink)'"
                                @mouseleave="$event.currentTarget.style.color = ''"
                            >
                                <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="remember" v-model:checked="form.remember" />
                        <label for="remember" class="text-sm t-muted">Ingat saya</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                        {{ form.processing ? 'Memproses…' : 'Masuk' }}
                    </button>
                </form>

                <p class="mt-6 text-xs text-center" style="color: var(--muted)">
                    Aktivitas Anda dicatat sesuai kebijakan keamanan organisasi.
                </p>
            </div>
        </div>
    </div>
</template> 