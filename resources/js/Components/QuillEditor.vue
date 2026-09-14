<script setup>
import { ref, onMounted, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Tulis di sini...' },
    uploadHandler: { type: Function, required: true },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
let quill = null;

// Selaras dengan URI.SafeIframeRegexp di RichContentSanitizer.
const allowedVideoUrl = /^https:\/\/(?:www\.youtube(?:-nocookie)?\.com\/embed\/[a-zA-Z0-9_-]+|player\.vimeo\.com\/video\/[0-9]+)(?:[?#][^\s]*)?$/;
const normalizeVideoUrl = (url) => {
    try {
        const parsed = new URL(url.trim());
        if (parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.port) return null;
        if (allowedVideoUrl.test(parsed.href)) return parsed.href;

        let id;
        if (['youtube.com', 'www.youtube.com'].includes(parsed.hostname)) {
            id = parsed.pathname === '/watch'
                ? parsed.searchParams.get('v')
                : parsed.pathname.match(/^\/(?:embed|live|shorts)\/([a-zA-Z0-9_-]+)$/)?.[1];
        } else if (parsed.hostname === 'youtu.be') {
            id = parsed.pathname.match(/^\/([a-zA-Z0-9_-]+)$/)?.[1];
        } else if (['vimeo.com', 'www.vimeo.com'].includes(parsed.hostname)) {
            id = parsed.pathname.match(/^\/([0-9]+)$/)?.[1];
            return id ? 'https://player.vimeo.com/video/' + id : null;
        }
        return id && /^[a-zA-Z0-9_-]+$/.test(id) ? 'https://www.youtube.com/embed/' + id : null;
    } catch {
        return null;
    }
};

const imageHandler = () => {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/jpeg,image/png,image/webp,image/gif');
    input.click();

    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const range = quill.getSelection(true);
        try {
            const url = await props.uploadHandler(file);
            quill.insertEmbed(range.index, 'image', url);
            quill.setSelection(range.index + 1);
        } catch (e) {
            alert('Upload gambar gagal: ' + e.message);
        }
    };
};

const videoHandler = () => {
    const raw = prompt('Paste URL YouTube atau Vimeo:');
    if (!raw) return;

    const normalized = normalizeVideoUrl(raw);
    if (!normalized || !allowedVideoUrl.test(normalized)) {
        alert('URL tidak dikenali sebagai YouTube atau Vimeo yang valid.');
        return;
    }

    const range = quill.getSelection(true);
    quill.insertEmbed(range.index, 'video', normalized);
    quill.setSelection(range.index + 1);
};

onMounted(() => {
    quill = new Quill(editorRef.value, {
        theme: 'snow',
        placeholder: props.placeholder,
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote'],
                    ['link', 'image', 'video'],
                    ['clean'],
                ],
                handlers: { image: imageHandler, video: videoHandler },
            },
        },
    });

    quill.on('text-change', () => {
        emit('update:modelValue', quill.root.innerHTML);
    });

    if (props.modelValue) {
        quill.root.innerHTML = props.modelValue;
    }
});

watch(() => props.modelValue, (newVal) => {
    if (quill && quill.root.innerHTML !== newVal) {
        quill.root.innerHTML = newVal;
    }
});
</script>

<template>
    <div ref="editorRef"></div>
</template>

<style scoped>
:deep(.ql-editor) {
    min-height: 400px;
}
:deep(.ql-video) {
    width: 100%;
    aspect-ratio: 16 / 9;
    max-width: 720px;
}
</style>
