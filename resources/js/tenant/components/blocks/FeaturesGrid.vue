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
    <div v-if="content.heading || content.subheading" class="text-center mb-10">
      <h2 v-if="content.heading" class="text-2xl md:text-3xl font-bold mb-3" :style="{ color: styleOverrides.heading_color || undefined }">
        {{ content.heading }}
      </h2>
      <p v-if="content.subheading" class="text-muted-foreground text-lg max-w-2xl mx-auto">{{ content.subheading }}</p>
    </div>
    <div
      class="grid gap-6 md:gap-8"
      :class="{
        'grid-cols-1 md:grid-cols-2': content.columns === '2',
        'grid-cols-1 md:grid-cols-3': content.columns === '3' || !content.columns,
        'grid-cols-1 md:grid-cols-2 lg:grid-cols-4': content.columns === '4',
      }"
    >
      <div
        v-for="(item, idx) in (content.items || [])"
        :key="idx"
        class="p-6 transition-shadow hover:shadow-md"
        :class="{
          'rounded-none': styleOverrides.card_radius === 'none',
          'rounded': styleOverrides.card_radius === 'sm',
          'rounded-lg': styleOverrides.card_radius === 'md' || !styleOverrides.card_radius,
          'rounded-xl': styleOverrides.card_radius === 'lg',
        }"
        :style="{ backgroundColor: styleOverrides.card_bg || undefined }"
      >
        <div v-if="item.icon" class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center mb-4 text-lg font-bold">
          {{ item.icon?.charAt?.(0)?.toUpperCase() || '★' }}
        </div>
        <h3 class="font-semibold text-lg mb-2">{{ item.title }}</h3>
        <p class="text-sm text-muted-foreground leading-relaxed">{{ item.description }}</p>
      </div>
    </div>
  </div>
</template>
