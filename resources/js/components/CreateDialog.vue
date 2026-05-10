<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogClose,
} from 'radix-vue'
import { Button } from '@/components/ui/button'
import { Save, X } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    open: boolean
    title?: string
    loading?: boolean
  }>(),
  {
    title: 'Create',
    loading: false,
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save'): void
}>()
</script>

<template>
  <DialogRoot :open="open" @update:open="(v) => { if (!v) emit('close') }">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-50 bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 border bg-background shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg max-h-[85vh] flex flex-col"
        :trap-focus="false"
        @pointer-down-outside="(e: any) => e.preventDefault()"
        @interact-outside="(e: any) => e.preventDefault()"
        @close-auto-focus="(e: Event) => e.preventDefault()"
      >
        <!-- Header -->
        <div class="flex items-center justify-between border-b px-6 py-4">
          <DialogTitle class="text-lg font-semibold">{{ title }}</DialogTitle>
          <DialogDescription class="sr-only">{{ title }}</DialogDescription>
          <DialogClose
            class="rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
          >
            <X class="h-4 w-4" />
            <span class="sr-only">Close</span>
          </DialogClose>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-6 py-4">
          <slot />
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 border-t px-6 py-4">
          <Button variant="outline" :disabled="loading" @click="emit('close')">
            Cancel
          </Button>
          <Button :disabled="loading" @click="emit('save')">
            <Save class="mr-2 h-4 w-4" />
            {{ loading ? 'Saving...' : 'Save' }}
          </Button>
        </div>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
