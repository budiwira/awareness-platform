<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm" style="color: var(--danger)">
            {{ message }}
        </p>
    </div>
</template>
