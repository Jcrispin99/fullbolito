<script setup lang="ts">
import { computed } from 'vue'
import { ImagePlus } from 'lucide-vue-next'

const props = defineProps<{
  content: Record<string, any>
  styleOverrides: Record<string, any>
  layout: Record<string, any>
  theme?: any
  editing?: boolean
}>()

const images = computed<any[]>(() => Array.isArray(props.content.images) ? props.content.images : [])

// In edit mode, if no images, show 6 placeholder cards so the block is visible
const placeholderCount = computed(() => {
  if (images.value.length > 0) return 0
  return props.editing ? 6 : 0
})
</script>

<template>
  <div class="px-6">
    <div
      class="grid"
      :class="{
        'grid-cols-2': content.columns === '2',
        'grid-cols-3': content.columns === '3' || !content.columns,
        'grid-cols-4': content.columns === '4',
        'gap-0': content.gap === 'none',
        'gap-2': content.gap === 'sm',
        'gap-4': content.gap === 'md' || !content.gap,
        'gap-6': content.gap === 'lg',
      }"
    >
      <div
        v-for="(img, idx) in images"
        :key="idx"
        class="aspect-square overflow-hidden bg-muted"
        :class="{
          'rounded-none': styleOverrides.border_radius === 'none',
          'rounded': styleOverrides.border_radius === 'sm',
          'rounded-lg': styleOverrides.border_radius === 'md' || !styleOverrides.border_radius,
          'rounded-xl': styleOverrides.border_radius === 'lg',
        }"
      >
        <img
          v-if="img?.url"
          :src="img.url"
          :alt="img.alt || ''"
          class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
        />
        <div v-else class="w-full h-full flex items-center justify-center text-muted-foreground text-xs">
          {{ idx + 1 }}
        </div>
      </div>

      <!-- Empty-state placeholders (only in edit mode) -->
      <div
        v-for="i in placeholderCount"
        :key="`ph-${i}`"
        class="aspect-square bg-muted/40 border-2 border-dashed border-muted-foreground/20 flex flex-col items-center justify-center text-muted-foreground/60 text-xs gap-1.5"
        :class="{
          'rounded-lg': !styleOverrides.border_radius || styleOverrides.border_radius === 'md',
        }"
      >
        <ImagePlus :size="22" :stroke-width="1.5" />
        <span v-if="i === 1" class="font-medium">Galería vacía</span>
        <span v-if="i === 1" class="text-[10px] opacity-70">Agrega imágenes desde el panel →</span>
      </div>
    </div>
  </div>
</template>
