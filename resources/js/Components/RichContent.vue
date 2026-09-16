<script setup>
defineProps({
    html: { type: String, default: '' },
});
</script>

<template>
    <div class="rich-content" v-html="html"></div>
</template>

<style scoped>
.rich-content {
    color: var(--ink);
    line-height: 1.75;
    overflow-wrap: anywhere;
}

.rich-content :deep(h2),
.rich-content :deep(h3),
.rich-content :deep(h4) {
    color: var(--ink);
    font-family: 'Inter', sans-serif;
    font-weight: 700;
    line-height: 1.3;
    margin: 1.75rem 0 0.75rem;
}

.rich-content :deep(h2) { font-size: 1.5rem; }
.rich-content :deep(h3) { font-size: 1.25rem; }
.rich-content :deep(h4) { font-size: 1.125rem; }

.rich-content :deep(p),
.rich-content :deep(ul),
.rich-content :deep(ol),
.rich-content :deep(blockquote) {
    margin: 0 0 1rem;
}

.rich-content :deep(ul),
.rich-content :deep(ol) {
    padding-inline-start: 1.5rem;
}

.rich-content :deep(ul) { list-style: disc; }
.rich-content :deep(ol) { list-style: decimal; }
.rich-content :deep(li + li) { margin-top: 0.375rem; }

.rich-content :deep(blockquote) {
    border-inline-start: 0.25rem solid var(--line);
    color: var(--muted);
    padding: 0.5rem 1rem;
}

.rich-content :deep(a) {
    color: var(--brand);
    text-decoration: underline;
    text-underline-offset: 0.2em;
}

.rich-content :deep(img) {
    display: block;
    height: auto;
    margin: 1.5rem auto;
    max-width: 100%;
}

.rich-content :deep(iframe) {
    aspect-ratio: 16 / 9;
    border: 0;
    display: block;
    height: auto;
    margin: 1.5rem auto;
    max-width: 100%;
    width: min(100%, 48rem);
}
</style>
