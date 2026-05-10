<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { SiteSection } from '@/types/builder'

const props = defineProps<{
  section: SiteSection
  editing: boolean
  selected: boolean
  containerWidth: number
  containerHeight: number
}>()

const emit = defineEmits<{
  select: [id: number]
  update: [payload: { position_x?: number; position_y?: number; element_width?: number; element_height?: number; rotation?: number }]
}>()

const el = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const isResizing = ref(false)
const isRotating = ref(false)
const dragStart = ref({ x: 0, y: 0, origX: 0, origY: 0 })
const resizeStart = ref({ x: 0, y: 0, origW: 0, origH: 0 })
const rotateStart = ref({ angle: 0, origRotation: 0, cx: 0, cy: 0 })

const isAbsolute = computed(() => props.section.position_mode === 'absolute')

const style = computed(() => {
  if (!isAbsolute.value) return {}
  return {
    position: 'absolute' as const,
    left: props.section.position_x + '%',
    top: props.section.position_y + '%',
    width: props.section.element_width ? props.section.element_width + '%' : 'auto',
    height: props.section.element_height ? props.section.element_height + 'px' : 'auto',
    transform: props.section.rotation ? `rotate(${props.section.rotation}deg)` : undefined,
    zIndex: props.selected ? 20 : 10,
  }
})

// --- Drag ---
function startDrag(e: MouseEvent) {
  if (!props.editing || !isAbsolute.value) return
  e.preventDefault()
  e.stopPropagation()
  emit('select', props.section.id)
  isDragging.value = true
  dragStart.value = {
    x: e.clientX,
    y: e.clientY,
    origX: props.section.position_x,
    origY: props.section.position_y,
  }
  window.addEventListener('mousemove', onDrag)
  window.addEventListener('mouseup', stopDrag)
}

function onDrag(e: MouseEvent) {
  if (!isDragging.value || !props.containerWidth) return
  const dx = ((e.clientX - dragStart.value.x) / props.containerWidth) * 100
  const dy = ((e.clientY - dragStart.value.y) / props.containerHeight) * 100
  emit('update', {
    position_x: Math.max(0, Math.min(100, dragStart.value.origX + dx)),
    position_y: Math.max(0, dragStart.value.origY + dy),
  })
}

function stopDrag() {
  isDragging.value = false
  window.removeEventListener('mousemove', onDrag)
  window.removeEventListener('mouseup', stopDrag)
}

// --- Resize ---
function startResize(e: MouseEvent) {
  if (!props.editing || !isAbsolute.value) return
  e.preventDefault()
  e.stopPropagation()
  isResizing.value = true
  resizeStart.value = {
    x: e.clientX,
    y: e.clientY,
    origW: props.section.element_width || 30,
    origH: props.section.element_height || (el.value?.offsetHeight || 100),
  }
  window.addEventListener('mousemove', onResize)
  window.addEventListener('mouseup', stopResize)
}

function onResize(e: MouseEvent) {
  if (!isResizing.value || !props.containerWidth) return
  const dxPct = ((e.clientX - resizeStart.value.x) / props.containerWidth) * 100
  const dy = e.clientY - resizeStart.value.y
  emit('update', {
    element_width: Math.max(10, resizeStart.value.origW + dxPct),
    element_height: Math.max(30, resizeStart.value.origH + dy),
  })
}

function stopResize() {
  isResizing.value = false
  window.removeEventListener('mousemove', onResize)
  window.removeEventListener('mouseup', stopResize)
}

// --- Rotate ---
function startRotate(e: MouseEvent) {
  if (!props.editing || !isAbsolute.value || !el.value) return
  e.preventDefault()
  e.stopPropagation()
  isRotating.value = true
  const rect = el.value.getBoundingClientRect()
  const cx = rect.left + rect.width / 2
  const cy = rect.top + rect.height / 2
  rotateStart.value = {
    angle: Math.atan2(e.clientY - cy, e.clientX - cx) * (180 / Math.PI),
    origRotation: props.section.rotation || 0,
    cx,
    cy,
  }
  window.addEventListener('mousemove', onRotate)
  window.addEventListener('mouseup', stopRotate)
}

function onRotate(e: MouseEvent) {
  if (!isRotating.value) return
  const { cx, cy, angle: startAngle, origRotation } = rotateStart.value
  const currentAngle = Math.atan2(e.clientY - cy, e.clientX - cx) * (180 / Math.PI)
  const delta = currentAngle - startAngle
  emit('update', { rotation: Math.round(origRotation + delta) })
}

function stopRotate() {
  isRotating.value = false
  window.removeEventListener('mousemove', onRotate)
  window.removeEventListener('mouseup', stopRotate)
}

function handleClick(e: MouseEvent) {
  if (props.editing) {
    e.stopPropagation()
    emit('select', props.section.id)
  }
}

onUnmounted(() => {
  stopDrag()
  stopResize()
  stopRotate()
})
</script>

<template>
  <div
    ref="el"
    class="canvas-element"
    :class="{
      'cursor-move': editing && isAbsolute,
      'ring-2 ring-primary': editing && selected,
      'hover:ring-1 hover:ring-primary/40': editing && !selected,
    }"
    :style="style"
    @mousedown="isAbsolute ? startDrag($event) : undefined"
    @click="handleClick"
  >
    <slot />

    <!-- Handles (only when selected + absolute mode) -->
    <template v-if="editing && selected && isAbsolute">
      <!-- Resize handle (bottom-right) -->
      <div
        class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-primary border-2 border-white rounded-sm cursor-nwse-resize z-30 shadow"
        @mousedown.stop="startResize"
      />
      <!-- Resize handle (bottom-left) -->
      <div
        class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-primary border-2 border-white rounded-sm cursor-nesw-resize z-30 shadow"
        @mousedown.stop="startResize"
      />
      <!-- Resize handle (top-right) -->
      <div
        class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-primary border-2 border-white rounded-sm cursor-nesw-resize z-30 shadow"
        @mousedown.stop="startResize"
      />

      <!-- Rotate handle (top-center) -->
      <div class="absolute -top-8 left-1/2 -translate-x-1/2 flex flex-col items-center z-30">
        <div
          class="w-5 h-5 bg-white border-2 border-primary rounded-full cursor-grab active:cursor-grabbing shadow flex items-center justify-center"
          @mousedown.stop="startRotate"
        >
          <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </div>
        <div class="w-px h-3 bg-primary" />
      </div>

      <!-- Info badge -->
      <div class="absolute -bottom-6 left-0 text-xs bg-zinc-800 text-white px-1.5 py-0.5 rounded whitespace-nowrap z-30">
        {{ Math.round(section.position_x) }}%, {{ Math.round(section.position_y) }}%
        <span v-if="section.rotation"> · {{ Math.round(section.rotation) }}°</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.canvas-element {
  transition: box-shadow 0.15s ease;
}
</style>
