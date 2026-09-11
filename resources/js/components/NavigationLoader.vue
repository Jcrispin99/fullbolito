<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { isNavigating } from '@/lib/navigationLoading'
</script>

<template>
  <Teleport to="body">
    <Transition name="navigation-loader">
      <div
        v-if="isNavigating"
        class="fixed inset-0 z-[99998]"
        role="status"
        aria-live="polite"
        aria-label="Cargando página"
      >
        <div
          class="absolute inset-0 bg-white/65 backdrop-blur-[2px] dark:bg-black/50"
          aria-hidden="true"
        />

        <Button
          disabled
          class="absolute right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] z-10 h-11 rounded-full px-5 shadow-xl disabled:opacity-100 sm:right-6 sm:bottom-[calc(1.5rem+env(safe-area-inset-bottom))]"
        >
          <Spinner class="size-4" />
          Cargando…
        </Button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.navigation-loader-enter-active,
.navigation-loader-leave-active {
  transition: opacity 180ms ease;
}

.navigation-loader-enter-from,
.navigation-loader-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .navigation-loader-enter-active,
  .navigation-loader-leave-active {
    transition-duration: 1ms;
  }
}
</style>
