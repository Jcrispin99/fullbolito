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
    class="flex flex-col items-center justify-center min-h-[400px] px-6"
    :class="{
      'min-h-[300px]': content.height === 'sm',
      'min-h-[400px]': content.height === 'md',
      'min-h-[560px]': content.height === 'lg',
      'min-h-screen': content.height === 'full',
      'items-start text-left': content.text_align === 'left',
      'items-center text-center': content.text_align === 'center' || !content.text_align,
      'items-end text-right': content.text_align === 'right',
    }"
  >
    <InlineText
      v-if="content.heading || editing"
      :model-value="content.heading"
      tag="h1"
      placeholder="Título principal"
      :editing="editing"
      as-html
      class="text-4xl md:text-5xl lg:text-6xl font-bold mb-4 max-w-4xl prose-inline"
      :style="{ color: styleOverrides.heading_color || undefined }"
      @update:model-value="(v) => update('heading', v)"
    />
    <InlineText
      v-if="content.subheading || editing"
      :model-value="content.subheading"
      tag="div"
      placeholder="Subtítulo descriptivo"
      :editing="editing"
      as-html
      class="text-lg md:text-xl text-muted-foreground mb-8 max-w-2xl prose-inline"
      @update:model-value="(v) => update('subheading', v)"
    />
    <a
      v-if="content.cta_text || editing"
      :href="!editing ? (content.cta_url || '#') : undefined"
      class="inline-flex items-center gap-2 px-8 py-3.5 rounded-md font-semibold text-lg transition-all hover:opacity-90"
      :class="{
        'border-2': content.cta_style === 'outline',
        'bg-transparent hover:underline': content.cta_style === 'ghost',
        'hover:scale-[1.02]': !editing,
      }"
      :style="{
        backgroundColor: content.cta_style !== 'outline' && content.cta_style !== 'ghost' ? (styleOverrides.cta_bg || undefined) : undefined,
        color: styleOverrides.cta_color || undefined,
        borderColor: content.cta_style === 'outline' ? (styleOverrides.cta_color || undefined) : undefined,
      }"
      @click="editing ? $event.preventDefault() : null"
    >
      <InlineText
        :model-value="content.cta_text"
        tag="span"
        placeholder="Texto del botón"
        :editing="editing"
        @update:model-value="(v) => update('cta_text', v)"
      />
    </a>
  </div>
</template>
