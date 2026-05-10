<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  content: Record<string, any>
  styleOverrides: Record<string, any>
  layout: Record<string, any>
  theme?: any
  editing?: boolean
}>()

const openIndex = ref<number | null>(null)

function toggle(idx: number) {
  openIndex.value = openIndex.value === idx ? null : idx
}
</script>

<template>
  <div class="px-6">
    <h2
      v-if="content.heading"
      class="text-2xl md:text-3xl font-bold text-center mb-8"
      :style="{ color: styleOverrides.heading_color || undefined }"
    >
      {{ content.heading }}
    </h2>
    <div class="max-w-3xl mx-auto divide-y">
      <div v-for="(item, idx) in (content.items || [])" :key="idx">
        <button
          class="w-full flex items-center justify-between py-4 text-left font-medium transition-colors hover:text-primary"
          :style="{ color: openIndex === idx ? undefined : (styleOverrides.question_color || undefined) }"
          @click="toggle(idx)"
        >
          <span>{{ item.question }}</span>
          <svg
            class="w-5 h-5 flex-shrink-0 transition-transform duration-200"
            :class="{ 'rotate-180': openIndex === idx }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <div
          class="overflow-hidden transition-all duration-200"
          :class="openIndex === idx ? 'max-h-96 pb-4' : 'max-h-0'"
        >
          <p
            class="text-muted-foreground leading-relaxed"
            :style="{ color: styleOverrides.answer_color || undefined }"
          >
            {{ item.answer }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
