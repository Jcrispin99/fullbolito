<script setup lang="ts">
import { cn } from "@/lib/utils"
import type { HTMLAttributes } from "vue"

const props = defineProps<{
  class?: HTMLAttributes["class"]
  label?: string
}>()
</script>

<template>
  <div
    data-sidebar="group"
    :class="cn('relative flex w-full min-w-0 flex-col p-2', props.class)"
  >
    <div
      v-if="label"
      data-sidebar="group-label"
      :class="cn(
        'duration-200 flex h-8 shrink-0 items-center rounded-md px-2 text-xs font-medium text-sidebar-foreground/70 outline-none ring-sidebar-ring transition-[margin,opa] ease-linear focus-visible:ring-2 [&>svg]:size-4 [&>svg]:shrink-0',
        'group-data-[collapsible=icon]:-mt-8 group-data-[collapsible=icon]:opacity-0',
        props.class
      )"
    >
      {{ label }}
    </div>
    <slot />
  </div>
</template>
