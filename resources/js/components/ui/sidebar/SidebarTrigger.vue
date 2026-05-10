<script setup lang="ts">
import { cn } from "@/lib/utils"
import { PanelLeft } from "lucide-vue-next"
import { type HTMLAttributes, inject } from "vue"
import { Button } from "@/components/ui/button"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { toggleSidebar } = inject("sidebar") as any
</script>

<template>
  <Button
    data-sidebar="trigger"
    variant="ghost"
    size="icon"
    :class="cn('h-7 w-7', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeft />
    <span class="sr-only">Toggle Sidebar</span>
  </Button>
</template>
