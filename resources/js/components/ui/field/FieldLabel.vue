<script setup lang="ts">
import { cn } from "@/lib/utils";
import { Label } from "@/components/ui/label";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  for?: string;
}>();
</script>

<template>
  <Label :for="props.for" :class="cn(props.class)">
    <slot />
  </Label>
</template>
