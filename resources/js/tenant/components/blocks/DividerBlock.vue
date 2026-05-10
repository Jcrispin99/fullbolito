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
    <hr
      class="border-t"
      :class="{
        'max-w-xs mx-auto': content.width === 'short',
        'max-w-2xl mx-auto': content.width === 'medium',
        '': content.width === 'full' || !content.width,
      }"
      :style="{
        borderColor: styleOverrides.color || undefined,
        borderTopWidth: (content.thickness || 1) + 'px',
      }"
    />
  </div>
</template>
