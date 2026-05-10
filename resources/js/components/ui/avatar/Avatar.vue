<script setup lang="ts">
import { cn } from "@/lib/utils"
import { AvatarRoot, type AvatarRootProps } from "radix-vue"
import { computed, type HTMLAttributes } from "vue"

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"] 
  size?: "sm" | "base" | "lg"
  shape?: "circle" | "square"
}>(), {
  size: "base",
  shape: "circle"
})

const sizeClass = computed(() => {
  if (props.size === "sm") return "h-8 w-8"
  if (props.size === "lg") return "h-12 w-12"
  return "h-10 w-10"
})

const shapeClass = computed(() => {
  return props.shape === "square" ? "rounded-md" : "rounded-full"
})
</script>

<template>
  <AvatarRoot :class="cn('relative flex shrink-0 overflow-hidden', sizeClass, shapeClass, props.class)">
    <slot />
  </AvatarRoot>
</template>
