<script setup lang="ts">
import { ref, nextTick, watch, onMounted } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string | null | undefined
  tag?: string
  placeholder?: string
  editing?: boolean
  asHtml?: boolean
}>(), {
  tag: 'span',
  placeholder: 'Escribe aquí...',
  editing: false,
  asHtml: false,
})

const emit = defineEmits<{
  'update:modelValue': [string]
}>()

const el = ref<HTMLElement | null>(null)
const isActive = ref(false)

function syncFromValue() {
  if (!el.value) return
  if (props.asHtml) {
    el.value.innerHTML = props.modelValue || ''
  } else {
    el.value.innerText = props.modelValue || ''
  }
}

onMounted(syncFromValue)
watch(() => props.modelValue, () => {
  if (!isActive.value) syncFromValue()
})

async function onDblClick(e: MouseEvent) {
  if (!props.editing) return
  e.stopPropagation()
  isActive.value = true
  await nextTick()
  if (el.value) {
    el.value.focus()
    const range = document.createRange()
    range.selectNodeContents(el.value)
    const sel = window.getSelection()
    sel?.removeAllRanges()
    sel?.addRange(range)
  }
}

function onBlur() {
  if (!isActive.value) return
  isActive.value = false
  const next = props.asHtml ? (el.value?.innerHTML || '') : (el.value?.innerText || '').trim()
  if (next !== (props.modelValue || '')) emit('update:modelValue', next)
}

function onKeyDown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey && !props.asHtml) {
    e.preventDefault()
    el.value?.blur()
  }
  if (e.key === 'Escape') {
    e.preventDefault()
    syncFromValue()
    isActive.value = false
    el.value?.blur()
  }
}

function onPaste(e: ClipboardEvent) {
  if (!props.asHtml) {
    e.preventDefault()
    const text = e.clipboardData?.getData('text/plain') || ''
    document.execCommand('insertText', false, text)
  }
}
</script>

<template>
  <component
    :is="tag"
    ref="el"
    :contenteditable="isActive ? (asHtml ? 'true' : 'plaintext-only') : false"
    :class="[
      editing && !isActive ? 'editor-inline-hover' : '',
      isActive ? 'editor-inline-active' : '',
    ]"
    :data-placeholder="placeholder"
    :data-empty="!modelValue ? 'true' : 'false'"
    @dblclick="onDblClick"
    @blur="onBlur"
    @keydown="onKeyDown"
    @paste="onPaste"
    @click="isActive ? $event.stopPropagation() : null"
    @mousedown="isActive ? $event.stopPropagation() : null"
  />
</template>

<style>
.editor-inline-hover {
  cursor: text;
  outline: 1px dashed transparent;
  outline-offset: 2px;
  border-radius: 2px;
  transition: outline-color 0.15s ease, background-color 0.15s ease;
}
.editor-inline-hover:hover {
  outline-color: rgb(99 102 241 / 0.4);
  background-color: rgb(99 102 241 / 0.04);
}
.editor-inline-active {
  outline: 2px solid rgb(99 102 241 / 0.6) !important;
  outline-offset: 2px;
  border-radius: 2px;
  background-color: rgb(99 102 241 / 0.04);
  cursor: text;
}
[data-empty="true"]:not(.editor-inline-active)::before {
  content: attr(data-placeholder);
  color: rgb(156 163 175);
  font-style: italic;
  pointer-events: none;
}
</style>
