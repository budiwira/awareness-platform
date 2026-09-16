<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="flex min-h-11 w-full items-center px-4 py-2 text-start text-sm leading-5 t-ink transition duration-150 ease-in-out hover:bg-surface2 focus:bg-surface2 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset"
    >
        <slot />
    </Link>
</template>
