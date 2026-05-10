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
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-center px-6">
    <!-- Image side (first on this variant) -->
    <div class="aspect-[4/3] bg-muted rounded-lg overflow-hidden">
      <img
        v-if="content.image?.url"
        :src="content.image.url"
        :alt="content.image?.alt || ''"
        class="w-full h-full"
        :class="{
          'object-cover': content.image_fit === 'cover' || !content.image_fit,
          'object-contain': content.image_fit === 'contain',
          'object-fill': content.image_fit === 'fill',
        }"
      />
      <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground text-sm">
        Imagen
      </div>
    </div>
    <!-- Text side -->
    <div>
      <h2
        v-if="content.heading"
        class="text-2xl md:text-3xl font-bold mb-4 prose-inline"
        :style="{ color: styleOverrides.heading_color || undefined }"
        v-html="content.heading"
      />
      <div
        v-if="content.body"
        class="text-muted-foreground leading-relaxed mb-6 prose prose-sm max-w-none"
        :style="{ color: styleOverrides.body_color || undefined }"
        v-html="content.body"
      />
      <a
        v-if="content.cta_text"
        :href="content.cta_url || '#'"
        class="inline-block px-6 py-2.5 bg-primary text-primary-foreground rounded-md font-medium hover:opacity-90 transition"
      >
        {{ content.cta_text }}
      </a>
    </div>
  </div>
</template>
