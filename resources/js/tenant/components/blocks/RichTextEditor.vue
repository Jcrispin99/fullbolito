<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import {
  Bold as BoldIcon,
  Italic as ItalicIcon,
  Strikethrough,
  List,
  ListOrdered,
  Link as LinkIcon,
  Undo2,
  Redo2,
  Heading1,
  Heading2,
  Heading3,
  Pilcrow,
  Quote,
} from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  modelValue: string | null | undefined
  placeholder?: string
  minimal?: boolean
}>(), {
  placeholder: 'Escribe aquí...',
  minimal: false,
})

const emit = defineEmits<{
  'update:modelValue': [string]
}>()

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    StarterKit.configure({
      heading: { levels: [1, 2, 3] },
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'text-primary underline',
      },
    }),
    Placeholder.configure({
      placeholder: props.placeholder,
      emptyEditorClass: 'is-editor-empty',
    }),
  ],
  onUpdate: ({ editor }) => {
    const html = editor.getHTML()
    emit('update:modelValue', html === '<p></p>' ? '' : html)
  },
  editorProps: {
    attributes: {
      class: 'prose prose-sm max-w-none focus:outline-none min-h-[60px] px-2.5 py-1.5',
    },
  },
})

watch(() => props.modelValue, (val) => {
  if (!editor.value) return
  const current = editor.value.getHTML()
  const next = val || ''
  if (current !== next && current.replace('<p></p>', '') !== next) {
    editor.value.commands.setContent(next, { emitUpdate: false })
  }
})

onBeforeUnmount(() => { editor.value?.destroy() })

function setLink() {
  if (!editor.value) return
  const previous = editor.value.getAttributes('link').href
  const url = window.prompt('URL del enlace', previous)
  if (url === null) return
  if (url === '') {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }
  editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}
</script>

<template>
  <div class="border rounded-md bg-background overflow-hidden focus-within:ring-1 focus-within:ring-primary">
    <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b px-1 py-1 bg-muted/30">
      <template v-if="!minimal">
        <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 1 }) ? 'is-active' : '']" title="Título 1" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">
          <Heading1 :size="14" :stroke-width="1.75" />
        </button>
        <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 2 }) ? 'is-active' : '']" title="Título 2" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
          <Heading2 :size="14" :stroke-width="1.75" />
        </button>
        <button type="button" :class="['toolbar-btn', editor.isActive('heading', { level: 3 }) ? 'is-active' : '']" title="Título 3" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
          <Heading3 :size="14" :stroke-width="1.75" />
        </button>
        <button type="button" :class="['toolbar-btn', editor.isActive('paragraph') ? 'is-active' : '']" title="Párrafo" @click="editor.chain().focus().setParagraph().run()">
          <Pilcrow :size="14" :stroke-width="1.75" />
        </button>
        <span class="w-px h-4 bg-border mx-0.5"></span>
      </template>

      <button type="button" :class="['toolbar-btn', editor.isActive('bold') ? 'is-active' : '']" title="Negrita (Cmd+B)" @click="editor.chain().focus().toggleBold().run()">
        <BoldIcon :size="14" :stroke-width="2" />
      </button>
      <button type="button" :class="['toolbar-btn', editor.isActive('italic') ? 'is-active' : '']" title="Cursiva (Cmd+I)" @click="editor.chain().focus().toggleItalic().run()">
        <ItalicIcon :size="14" :stroke-width="1.75" />
      </button>
      <button type="button" :class="['toolbar-btn', editor.isActive('strike') ? 'is-active' : '']" title="Tachado" @click="editor.chain().focus().toggleStrike().run()">
        <Strikethrough :size="14" :stroke-width="1.75" />
      </button>
      <span class="w-px h-4 bg-border mx-0.5"></span>

      <button type="button" :class="['toolbar-btn', editor.isActive('bulletList') ? 'is-active' : '']" title="Lista" @click="editor.chain().focus().toggleBulletList().run()">
        <List :size="14" :stroke-width="1.75" />
      </button>
      <button type="button" :class="['toolbar-btn', editor.isActive('orderedList') ? 'is-active' : '']" title="Lista numerada" @click="editor.chain().focus().toggleOrderedList().run()">
        <ListOrdered :size="14" :stroke-width="1.75" />
      </button>
      <button v-if="!minimal" type="button" :class="['toolbar-btn', editor.isActive('blockquote') ? 'is-active' : '']" title="Cita" @click="editor.chain().focus().toggleBlockquote().run()">
        <Quote :size="14" :stroke-width="1.75" />
      </button>
      <button type="button" :class="['toolbar-btn', editor.isActive('link') ? 'is-active' : '']" title="Enlace" @click="setLink">
        <LinkIcon :size="14" :stroke-width="1.75" />
      </button>

      <span class="ml-auto flex items-center gap-0.5">
        <button type="button" class="toolbar-btn" title="Deshacer (Cmd+Z)" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">
          <Undo2 :size="14" :stroke-width="1.75" />
        </button>
        <button type="button" class="toolbar-btn" title="Rehacer (Cmd+Shift+Z)" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">
          <Redo2 :size="14" :stroke-width="1.75" />
        </button>
      </span>
    </div>
    <EditorContent :editor="editor" />
  </div>
</template>

<style scoped>
.toolbar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 4px;
  color: rgb(113 113 122);
  transition: background-color 0.15s, color 0.15s;
}
.toolbar-btn:hover:not(:disabled) {
  background-color: rgb(244 244 245);
  color: rgb(24 24 27);
}
.toolbar-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.toolbar-btn.is-active {
  background-color: rgb(99 102 241 / 0.12);
  color: rgb(79 70 229);
}
:deep(.ProseMirror) {
  outline: none;
}
:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  color: rgb(161 161 170);
  pointer-events: none;
  height: 0;
  float: left;
}
</style>
