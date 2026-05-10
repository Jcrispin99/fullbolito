<script setup lang="ts">
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { ref } from 'vue'

const open = ref(false)
const title = ref('')
const message = ref('')
const onConfirm = ref<() => Promise<void> | void>(() => {})
const isLoading = ref(false)

const show = (t: string, m: string, confirmFn: () => Promise<void> | void) => {
    title.value = t
    message.value = m
    onConfirm.value = confirmFn
    if (typeof window !== 'undefined') {
        const active = document.activeElement as HTMLElement | null
        active?.blur?.()
        window.requestAnimationFrame(() => {
            open.value = true
        })
        return
    }

    open.value = true
}

const handleConfirm = async () => {
    try {
        isLoading.value = true
        await onConfirm.value()
        open.value = false
    } catch (e) {
        console.error(e)
    } finally {
        isLoading.value = false
    }
}

defineExpose({ show })
</script>

<template>
  <AlertDialog :open="open" @update:open="open = $event">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>
           {{ message }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="isLoading">Cancel</AlertDialogCancel>
        <AlertDialogAction @click.prevent="handleConfirm" :disabled="isLoading">
            <span v-if="isLoading">Processing...</span>
            <span v-else>Continue</span>
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
