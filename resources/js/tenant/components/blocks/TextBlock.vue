<script setup lang="ts">
import InlineText from './InlineText.vue'

defineProps<{
  content: Record<string, any>
  styleOverrides: Record<string, any>
  layout: Record<string, any>
  theme?: any
  editing?: boolean
}>()

const emit = defineEmits<{
  updateField: [{ key: string; value: any }]
}>()

function update(key: string, value: any) {
  emit('updateField', { key, value })
}
</script>

<template>
  <div
    class="px-6"
    :class="{
      'text-left': content.text_align === 'left' || !content.text_align,
      'text-center': content.text_align === 'center',
      'text-right': content.text_align === 'right',
    }"
  >
    <InlineText
      v-if="content.heading || editing"
      :model-value="content.heading"
      tag="h2"
      placeholder="Título (opcional)"
      :editing="editing"
      as-html
      class="text-2xl md:text-3xl font-bold mb-4 prose-inline"
      :style="{ color: styleOverrides.heading_color || undefined }"
      @update:model-value="(v) => update('heading', v)"
    />
    <InlineText
      :model-value="content.body"
      tag="div"
      placeholder="Escribe tu contenido aquí..."
      :editing="editing"
      as-html
      class="prose prose-lg max-w-none text-muted-foreground leading-relaxed"
      :style="{ color: styleOverrides.body_color || undefined }"
      @update:model-value="(v) => update('body', v)"
    />
  </div>
</template>
