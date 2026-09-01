<script setup>
import { ref } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    loginStreak: Number,
    earnedBadges: Number,
});

const page = usePage();
const user = page.props.auth.user;

const form = useForm({
    show_on_leaderboard: user.show_on_leaderboard ?? true,
});

const updateLeaderboardPreference = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Update berhasil
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium t-ink">Gamifikasi</h2>
            <p class="mt-1 text-sm t-muted">
                Statistik badge, streak, dan preferensi leaderboard Anda.
            </p>
        </header>

        <div class="mt-6 space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Streak -->
                <div class="card p-4 flex items-center gap-3">
                    <div class="text-3xl">🔥</div>
                    <div>
                        <div class="text-2xl font-display font-bold t-brand">{{ loginStreak }}</div>
                        <div class="text-xs t-muted">Hari Streak</div>
                    </div>
                </div>

                <!-- Badges -->
                <Link :href="route('user.badges.index')" class="card p-4 flex items-center gap-3 hover:shadow-md transition-shadow">
                    <div class="text-3xl">🏆</div>
                    <div>
                        <div class="text-2xl font-display font-bold t-brand">{{ earnedBadges }}</div>
                        <div class="text-xs t-muted">Badge Terkumpul</div>
                    </div>
                </Link>
            </div>

            <!-- Leaderboard Opt-out -->
            <div class="card p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input 
                        type="checkbox" 
                        v-model="form.show_on_leaderboard"
                        @change="updateLeaderboardPreference"
                        class="mt-1"
                    />
                    <div>
                        <div class="font-medium t-ink">Tampilkan di Leaderboard</div>
                        <p class="text-sm t-muted">
                            Jika dinonaktifkan, nama Anda tidak akan muncul di leaderboard organisasi.
                        </p>
                    </div>
                </label>
            </div>

            <div class="flex gap-3">
                <Link 
                    :href="route('user.badges.index')" 
                    class="btn btn-secondary"
                >
                    Lihat Semua Badge
                </Link>
                <Link 
                    :href="route('user.leaderboard.index')" 
                    class="btn btn-secondary"
                >
                    Lihat Leaderboard
                </Link>
            </div>
        </div>
    </section>
</template>
