<script setup>
import { computed } from 'vue';
import BaseBadge from '@/Components/BaseBadge.vue';

const props = defineProps({
    injects: { type: Array, required: true },
    selectedId: { type: [Number, null], default: null },
    currentInjectOrder: { type: [Number, null], default: null },
});

const emit = defineEmits(['select']);

const statusConfig = {
    pending: { variant: 'neutral', label: 'Pending' },
    active: { variant: 'warning', label: 'Aktif' },
    locked: { variant: 'success', label: 'Selesai' },
};

const isSelected = (inject) => inject.id === props.selectedId;
const isCurrent = (inject) => inject.order === props.currentInjectOrder;
</script>

<template>
    <nav aria-label="Timeline Injeksi" class="inject-timeline">
        <ol class="timeline-list" role="list">
            <li
                v-for="inject in injects"
                :key="inject.id"
                class="timeline-item"
            >
                <button
                    type="button"
                    class="timeline-btn"
                    :class="{
                        'timeline-btn--selected': isSelected(inject),
                        'timeline-btn--active': isCurrent(inject),
                    }"
                    :aria-current="isCurrent(inject) ? 'step' : undefined"
                    @click="emit('select', inject.id)"
                >
                    <span class="timeline-order">{{ inject.order }}</span>
                    <span class="timeline-content">
                        <span class="timeline-title">{{ inject.snapshot?.title ?? `Injeksi #${inject.order}` }}</span>
                        <BaseBadge :variant="statusConfig[inject.status]?.variant ?? 'neutral'" size="sm">
                            {{ statusConfig[inject.status]?.label ?? inject.status }}
                        </BaseBadge>
                    </span>
                </button>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.inject-timeline {
    width: 100%;
}

.timeline-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: var(--sp-2);
}

.timeline-item {
    position: relative;
}

.timeline-btn {
    display: flex;
    align-items: flex-start;
    gap: var(--sp-3);
    width: 100%;
    padding: var(--sp-3) var(--sp-4);
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    background: var(--surface);
    color: var(--ink);
    text-align: left;
    cursor: pointer;
    transition: background var(--dur) var(--ease), border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
    min-width: 0;
}

.timeline-btn:hover {
    background: var(--surface-2);
    border-color: var(--brand);
}

.timeline-btn:focus-visible {
    outline: 2px solid var(--brand);
    outline-offset: 2px;
}

.timeline-btn--selected {
    border-color: var(--brand);
    background: var(--brand-soft);
    box-shadow: var(--glow);
}

.timeline-btn--active {
    border-left: 3px solid var(--warn);
}

.timeline-order {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 28px;
    height: 28px;
    border-radius: var(--r-full);
    background: var(--surface-2);
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 600;
}

.timeline-btn--active .timeline-order {
    background: var(--warn);
    color: var(--ink);
}

.timeline-btn--selected .timeline-order {
    background: var(--brand);
    color: var(--white);
}

.timeline-content {
    display: flex;
    flex-direction: column;
    gap: var(--sp-1);
    min-width: 0;
}

.timeline-title {
    font-size: 0.875rem;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}

@media (prefers-reduced-motion: reduce) {
    .timeline-btn {
        transition: none;
    }
}
</style>
