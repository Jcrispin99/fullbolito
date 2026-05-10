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
  <div class="px-6">
    <div
      class="overflow-hidden"
      :class="{
        'rounded-none': styleOverrides.border_radius === 'none',
        'rounded': styleOverrides.border_radius === 'sm',
        'rounded-lg': styleOverrides.border_radius === 'md' || !styleOverrides.border_radius,
        'rounded-xl': styleOverrides.border_radius === 'lg',
        'rounded-full': styleOverrides.border_radius === 'full',
      }"
      :style="{ maxHeight: content.max_height ? content.max_height + 'px' : undefined }"
    >
      <img
        v-if="content.image?.url"
        :src="content.image.url"
        :alt="content.image?.alt || content.caption || ''"
        class="w-full"
        :class="{
          'object-cover': content.image_fit === 'cover' || !content.image_fit,
          'object-contain': content.image_fit === 'contain',
          'object-fill': content.image_fit === 'fill',
        }"
      />
      <div v-else class="w-full h-64 bg-muted flex items-center justify-center text-muted-foreground text-sm">
        Imagen
      </div>
    </div>
    <p v-if="content.caption" class="mt-3 text-sm text-muted-foreground text-center">
      {{ content.caption }}
    </p>
  </div>
</template>
