<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BaseBadge from "@/Components/BaseBadge.vue";
import EmptyState from "@/Components/EmptyState.vue";

defineProps({
    sessions: { type: Array, required: true },
});

const statusConfig = {
    draft: { label: "Draft", variant: "neutral" },
    ready: { label: "Siap", variant: "info" },
    in_progress: { label: "Berlangsung", variant: "warning" },
    debrief: { label: "Debrief", variant: "brand" },
    completed: { label: "Selesai", variant: "success" },
};
</script>

<template>
    <Head title="Tabletop" />

    <AppLayout title="Exercise Room">
        <div class="tabletop-page fade-in">
            <header class="tabletop-hero">
                <p class="tabletop-eyebrow">Tabletop Exercise</p>
                <h1 class="font-display text-2xl font-bold t-ink sm:text-3xl">
                    Exercise Room
                </h1>
                <p class="tabletop-description">
                    Buka sesi yang ditugaskan kepada Anda untuk mengikuti
                    exercise atau meninjau timeline yang telah dirilis.
                </p>
            </header>

            <div v-if="sessions.length" class="session-list" role="list">
                <article
                    v-for="session in sessions"
                    :key="session.id"
                    class="session-card"
                    role="listitem"
                >
                    <div class="session-content">
                        <div class="session-heading">
                            <h2
                                class="font-display text-lg font-semibold t-ink"
                            >
                                {{ session.title }}
                            </h2>
                            <BaseBadge
                                :variant="
                                    statusConfig[session.status]?.variant ??
                                    'neutral'
                                "
                            >
                                {{
                                    statusConfig[session.status]?.label ??
                                    session.status
                                }}
                            </BaseBadge>
                        </div>
                        <p class="session-role">{{ session.scenario }}</p>
                        <p class="session-role">
                            Tim: {{ session.team_name ?? "Belum ditetapkan" }} ·
                            Fasilitator: {{ session.facilitator_name }}
                        </p>
                    </div>

                    <Link :href="session.action_url" class="session-action">
                        <span>{{ session.action_label }}</span>
                        <svg
                            class="h-4 w-4"
                            aria-hidden="true"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12h14m-6-6l6 6-6 6"
                            />
                        </svg>
                    </Link>
                </article>
            </div>

            <EmptyState
                v-else
                title="Belum ada sesi Tabletop"
                message="Exercise akan muncul setelah Anda ditugaskan pada tim dan session siap."
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.tabletop-page {
    display: flex;
    flex-direction: column;
    gap: var(--sp-6);
}

.tabletop-hero {
    padding: var(--sp-6);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    background: var(--surface);
}

.tabletop-eyebrow {
    margin: 0 0 var(--sp-1);
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.tabletop-description {
    max-width: 44rem;
    margin: var(--sp-2) 0 0;
    color: var(--muted);
    line-height: 1.6;
}

.session-list {
    display: grid;
    gap: var(--sp-4);
}

.session-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-5);
    min-width: 0;
    padding: var(--sp-5);
    border: 1px solid var(--line);
    border-radius: var(--r-xl);
    background: var(--surface);
    box-shadow: var(--shadow-sm);
}

.session-content {
    min-width: 0;
}

.session-heading {
    display: flex;
    align-items: center;
    gap: var(--sp-3);
    flex-wrap: wrap;
}

.session-role {
    margin: var(--sp-2) 0 0;
    color: var(--muted);
    font-size: 0.875rem;
}

.session-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--sp-2);
    min-height: 44px;
    flex: 0 0 auto;
    padding: var(--sp-2) var(--sp-4);
    border: 1px solid var(--brand);
    border-radius: var(--r-full);
    background: var(--brand);
    color: var(--white);
    font-size: 0.875rem;
    font-weight: 600;
    transition:
        background var(--dur) var(--ease),
        transform var(--dur-fast) var(--ease),
        box-shadow var(--dur) var(--ease);
}

.session-action:hover {
    background: var(--brand-strong);
}

.session-action:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}

.session-action:active {
    transform: translateY(1px);
}

@media (max-width: 640px) {
    .tabletop-hero,
    .session-card {
        padding: var(--sp-4);
    }

    .session-card {
        align-items: stretch;
        flex-direction: column;
    }

    .session-action {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .session-action {
        transition: none;
    }
}
</style>
