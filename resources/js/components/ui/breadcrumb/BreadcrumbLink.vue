<script setup lang="ts">
import { cn } from "@/lib/utils"
import type { HTMLAttributes } from "vue"
import { Primitive } from "radix-vue"

const props = withDefaults(
  defineProps<{
    as?: string
    href?: string
    class?: HTMLAttributes["class"]
    asChild?: boolean
  }>(),
  {
    as: "a",
  }
)
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    :href="href"
    :class="cn('transition-colors hover:text-foreground', props.class)"
  >
    <slot />
  </Primitive>
</template>
