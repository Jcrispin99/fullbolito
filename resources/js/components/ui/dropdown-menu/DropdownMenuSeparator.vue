<script setup lang="ts">
import { cn } from "@/lib/utils"
import { DropdownMenuSeparator, type DropdownMenuSeparatorProps, useForwardProps } from "radix-vue"
import { computed, type HTMLAttributes } from "vue"

const props = defineProps<DropdownMenuSeparatorProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props
  return delegated
})

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <DropdownMenuSeparator v-bind="forwarded" :class="cn('-mx-1 my-1 h-px bg-muted', props.class)" />
</template>
