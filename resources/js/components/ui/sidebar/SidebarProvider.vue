<script lang="ts">
export const SIDEBAR_COOKIE_NAME = "sidebar:state"
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
export const SIDEBAR_WIDTH = "16rem"
export const SIDEBAR_WIDTH_MOBILE = "18rem"
export const SIDEBAR_WIDTH_ICON = "3rem"
export const SIDEBAR_KEYBOARD_SHORTCUT = "b"
</script>

<script setup lang="ts">
import { cn } from "@/lib/utils"
import { useStorage, useMediaQuery } from "@vueuse/core"
import { type HTMLAttributes, type Ref, computed, provide, ref, toRef } from "vue"

const props = withDefaults(
  defineProps<{
    defaultOpen?: boolean
    open?: boolean
    class?: HTMLAttributes["class"]
  }>(),
  {
    defaultOpen: true,
    open: undefined,
  }
)

const emits = defineEmits<{
  "update:open": [boolean]
}>()

const isMobile = useMediaQuery("(max-width: 768px)")
const openMobile = ref(false)

const openState = useStorage(SIDEBAR_COOKIE_NAME, props.defaultOpen)
const open = computed({
  get: () => props.open ?? openState.value,
  set: (value) => {
    openState.value = value
    emits("update:open", value)
  },
})

function setOpen(value: boolean) {
  open.value = value
}

function setOpenMobile(value: boolean) {
  openMobile.value = value
}

function toggleSidebar() {
  return isMobile.value
    ? setOpenMobile(!openMobile.value)
    : setOpen(!open.value)
}

const state = computed(() => open.value ? "expanded" : "collapsed")

provide("sidebar", {
  state,
  open,
  setOpen,
  isMobile,
  openMobile,
  setOpenMobile,
  toggleSidebar,
})
</script>

<template>
  <div
    :class="cn('group/sidebar-wrapper flex min-h-svh w-full has-[[data-variant=inset]]:bg-sidebar', props.class)"
    :style="{
      '--sidebar-width': SIDEBAR_WIDTH,
      '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
    }"
  >
    <slot />
  </div>
</template>
