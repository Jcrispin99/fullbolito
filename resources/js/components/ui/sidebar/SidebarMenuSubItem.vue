<script setup lang="ts">
import type { HTMLAttributes } from "vue"

defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <li :class="$props.class">
    <slot />
  </li>
</template>
