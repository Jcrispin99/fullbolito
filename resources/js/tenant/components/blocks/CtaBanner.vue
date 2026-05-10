<script setup lang="ts">
defineProps<{
  content: Record<string, any>
  styleOverrides: Record<string, any>
  layout: Record<string, any>
  theme?: any
  editing?: boolean
}>()
</script>

<template>
  <div class="px-6 text-center">
    <h2
      v-if="content.heading"
      class="text-2xl md:text-3xl lg:text-4xl font-bold mb-4 prose-inline"
      :style="{ color: styleOverrides.heading_color || undefined }"
      v-html="content.heading"
    />
    <div
      v-if="content.body"
      class="text-lg mb-8 max-w-2xl mx-auto prose prose-sm prose-invert max-w-none"
      :style="{ color: styleOverrides.body_color || undefined }"
      v-html="content.body"
    />
    <a
      v-if="content.cta_text"
      :href="content.cta_url || '#'"
      class="inline-block px-8 py-3.5 rounded-md font-semibold text-lg transition-all hover:opacity-90 hover:scale-[1.02]"
      :class="{
        'border-2': content.cta_style === 'outline',
        'bg-transparent hover:underline': content.cta_style === 'ghost',
      }"
      :style="{
        backgroundColor: content.cta_style !== 'outline' && content.cta_style !== 'ghost' ? (styleOverrides.cta_bg || undefined) : undefined,
        color: styleOverrides.cta_color || undefined,
        borderColor: content.cta_style === 'outline' ? (styleOverrides.cta_color || undefined) : undefined,
      }"
    >
      {{ content.cta_text }}
    </a>
  </div>
</template>
