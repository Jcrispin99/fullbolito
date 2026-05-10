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
    <h2 v-if="content.heading" class="text-2xl md:text-3xl font-bold text-center mb-10">{{ content.heading }}</h2>
    <div
      class="grid gap-8"
      :class="{
        'grid-cols-2': content.columns === '2',
        'grid-cols-2 md:grid-cols-3': content.columns === '3' || !content.columns,
        'grid-cols-2 md:grid-cols-4': content.columns === '4',
      }"
    >
      <div v-for="(item, idx) in (content.items || [])" :key="idx" class="text-center">
        <div
          class="font-bold mb-1"
          :class="{
            'text-2xl': styleOverrides.value_size === 'md',
            'text-3xl md:text-4xl': styleOverrides.value_size === 'lg' || !styleOverrides.value_size,
            'text-4xl md:text-5xl': styleOverrides.value_size === 'xl',
            'text-5xl md:text-6xl': styleOverrides.value_size === '2xl',
          }"
          :style="{ color: styleOverrides.value_color || undefined }"
        >
          {{ item.value }}
        </div>
        <div class="text-sm text-muted-foreground" :style="{ color: styleOverrides.label_color || undefined }">
          {{ item.label }}
        </div>
      </div>
    </div>
  </div>
</template>
