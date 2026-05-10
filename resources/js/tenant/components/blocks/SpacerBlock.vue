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
  <div
    :style="{ height: (content.height || 60) + 'px' }"
    :class="{ 'border border-dashed border-muted-foreground/30': editing }"
  />
</template>
