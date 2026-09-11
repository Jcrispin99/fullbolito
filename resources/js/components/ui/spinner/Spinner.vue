<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <LoaderCircle
    aria-hidden="true"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
