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

// watch?v=XXX / youtu.be/XXX -> youtube.com/embed/XXX ; vimeo.com/X -> player.vimeo.com/video/X
const normalizeVideoUrl = (url) => {
    let m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    if (m) return 'https://www.youtube.com/embed/' + m[1];
    m = url.match(/vimeo\.com\/(\d+)/);
    if (m) return 'https://player.vimeo.com/video/' + m[1];
    return url;
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
    const url = prompt('Paste URL YouTube atau Vimeo:');
    if (!url) return;
    const normalized = normalizeVideoUrl(url.trim());
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
</style>