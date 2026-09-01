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
        class="block w-full px-4 py-2 text-start text-sm leading-5 t-ink transition duration-150 ease-in-out hover:bg-surface2 focus:bg-surface2 focus:outline-none"
    >
        <slot />
    </Link>
</template>
