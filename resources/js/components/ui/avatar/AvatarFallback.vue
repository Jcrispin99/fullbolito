<script setup lang="ts">
import { cn } from "@/lib/utils"
import { AvatarFallback as AvatarFallbackRoot, type AvatarFallbackProps } from "radix-vue"
import type { HTMLAttributes } from "vue"

const props = defineProps<AvatarFallbackProps & { class?: HTMLAttributes["class"] }>()
</script>

<template>
  <AvatarFallbackRoot v-bind="props" :class="cn('flex h-full w-full items-center justify-center bg-muted', props.class)">
    <slot />
  </AvatarFallbackRoot>
</template>
