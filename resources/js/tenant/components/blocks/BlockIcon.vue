<script setup lang="ts">
import { computed } from 'vue'
import {
  Image,
  Type,
  Columns2,
  PanelLeft,
  PanelRight,
  Heading,
  LayoutGrid,
  Grid3x3,
  ChartColumnBig,
  Megaphone,
  CircleHelp,
  Mail,
  Calendar,
  MoveVertical,
  Minus,
  Square,
  Star,
  Zap,
  Shield,
  type LucideIcon,
} from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  name: string | null | undefined
  size?: number
  strokeWidth?: number
}>(), {
  size: 18,
  strokeWidth: 1.75,
})

const map: Record<string, LucideIcon> = {
  'image': Image,
  'type': Type,
  'columns': Columns2,
  'panel-left': PanelLeft,
  'panel-right': PanelRight,
  'heading': Heading,
  'layout-grid': LayoutGrid,
  'grid-3x3': Grid3x3,
  'bar-chart-3': ChartColumnBig,
  'megaphone': Megaphone,
  'help-circle': CircleHelp,
  'mail': Mail,
  'calendar': Calendar,
  'move-vertical': MoveVertical,
  'minus': Minus,
  'star': Star,
  'zap': Zap,
  'shield': Shield,
}

const component = computed<LucideIcon>(() => {
  if (!props.name) return Square
  return map[props.name] || Square
})
</script>

<template>
  <component :is="component" :size="size" :stroke-width="strokeWidth" />
</template>
