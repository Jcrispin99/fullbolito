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
      :model-value="content.text"
      :tag="content.level || 'h2'"
      placeholder="Título"
      :editing="editing"
      class="font-bold"
      :class="{
        'text-4xl md:text-5xl': content.level === 'h1',
        'text-3xl md:text-4xl': content.level === 'h2' || !content.level,
        'text-2xl md:text-3xl': content.level === 'h3',
        'text-xl md:text-2xl': content.level === 'h4',
        'text-lg md:text-xl': content.level === 'h5',
        'text-base md:text-lg': content.level === 'h6',
      }"
      :style="{ color: styleOverrides.heading_color || undefined }"
      @update:model-value="(v) => update('text', v)"
    />
    <InlineText
      v-if="content.subtitle || editing"
      :model-value="content.subtitle"
      tag="p"
      placeholder="Subtítulo (opcional)"
      :editing="editing"
      class="mt-3 text-lg text-muted-foreground"
      :style="{ color: styleOverrides.subtitle_color || undefined }"
      @update:model-value="(v) => update('subtitle', v)"
    />
  </div>
</template>
