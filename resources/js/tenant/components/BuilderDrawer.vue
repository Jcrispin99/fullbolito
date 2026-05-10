<script setup lang="ts">
defineProps<{
  title: string
  width?: string
}>()

defineEmits<{
  close: []
}>()
</script>

<template>
  <Transition name="slide">
    <div
      class="fixed top-11 right-0 bottom-0 bg-background border-l shadow-xl z-[90] overflow-y-auto"
      :class="width || 'w-80'"
    >
      <div class="sticky top-0 bg-background border-b px-4 py-3 flex items-center justify-between z-10">
        <h3 class="text-sm font-semibold">{{ title }}</h3>
        <button class="text-muted-foreground hover:text-foreground" @click="$emit('close')">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="p-4">
        <slot />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: transform 0.2s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
}
</style>
