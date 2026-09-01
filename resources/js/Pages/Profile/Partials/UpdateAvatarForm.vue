<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const user = usePage().props.auth.user;
const fileInput = ref(null);
const previewUrl = ref(user.avatar_path ? `/storage/${user.avatar_path}` : null);

const form = useForm({
    avatar: null,
});

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            previewUrl.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const submit = () => {
    form.post(route('profile.avatar'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
};

const initials = (user?.name ?? '?')
    .split(' ')
    .map((s) => s[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium t-ink">
                Foto Profil
            </h2>

            <p class="mt-1 text-sm t-muted">
                Upload foto profil Anda (JPG, PNG, WEBP, max 2MB).
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="flex items-center gap-6">
                <div 
                    v-if="previewUrl"
                    class="w-24 h-24 rounded-full ring-4 overflow-hidden bg-cover bg-center"
                    :style="{ backgroundImage: `url(${previewUrl})`, ringColor: 'var(--brand)' }"
                ></div>
                <div 
                    v-else
                    class="w-24 h-24 rounded-full text-2xl font-bold flex items-center justify-center ring-4"
                    style="background: var(--brand); color: var(--white); ring-color: var(--brand)"
                >
                    {{ initials }}
                </div>

                <div>
                    <input 
                        ref="fileInput"
                        type="file" 
                        accept="image/jpeg,image/jpg,image/png,image/webp"
                        @change="handleFileChange"
                        class="hidden"
                    />
                    <button
                        type="button"
                        @click="fileInput.click()"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                        style="background: var(--surface2); color: var(--ink)"
                    >
                        Pilih Foto
                    </button>
                    <p class="mt-2 text-xs t-muted">
                        JPG, PNG, atau WEBP. Maksimal 2MB.
                    </p>
                </div>
            </div>

            <InputError class="mt-2" :message="form.errors.avatar" />

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing || !form.avatar">
                    Upload
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm t-muted"
                    >
                        Tersimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
