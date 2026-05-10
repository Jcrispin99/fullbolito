<script setup lang="ts">
import { ref } from 'vue'
import { apiClient } from '@tenant/lib/api'

const props = defineProps<{
  content: Record<string, any>
  styleOverrides: Record<string, any>
  layout: Record<string, any>
  theme?: any
  editing?: boolean
}>()

const formData = ref<Record<string, string>>({})
const isSubmitting = ref(false)
const submitted = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  if (props.editing) return
  isSubmitting.value = true
  error.value = null
  try {
    await apiClient.post('/v1/public/forms/submit', {
      form_type: 'contact',
      data: formData.value,
    })
    submitted.value = true
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al enviar'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="px-6">
    <h2
      v-if="content.heading"
      class="text-2xl md:text-3xl font-bold mb-3"
      :class="layout.text_align === 'center' ? 'text-center' : ''"
      :style="{ color: styleOverrides.heading_color || undefined }"
    >
      {{ content.heading }}
    </h2>
    <p v-if="content.description" class="text-muted-foreground mb-6" :class="layout.text_align === 'center' ? 'text-center' : ''">
      {{ content.description }}
    </p>

    <!-- Success message -->
    <div v-if="submitted" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 text-center">
      <p class="text-green-700 dark:text-green-300 font-medium">{{ content.success_message || 'Enviado correctamente' }}</p>
    </div>

    <!-- Form -->
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div v-for="field in (content.fields || [])" :key="field.name">
        <label class="block text-sm font-medium mb-1.5">
          {{ field.label }}
          <span v-if="field.required" class="text-destructive">*</span>
        </label>
        <textarea
          v-if="field.type === 'textarea'"
          v-model="formData[field.name]"
          :required="field.required"
          :placeholder="field.label"
          rows="4"
          class="w-full px-4 py-2.5 border rounded-lg bg-background text-sm resize-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
        />
        <input
          v-else
          v-model="formData[field.name]"
          :type="field.type || 'text'"
          :required="field.required"
          :placeholder="field.label"
          class="w-full px-4 py-2.5 border rounded-lg bg-background text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
        />
      </div>
      <div v-if="error" class="text-sm text-destructive">{{ error }}</div>
      <button
        type="submit"
        class="w-full px-6 py-3 rounded-lg font-semibold text-sm transition-all hover:opacity-90"
        :disabled="isSubmitting || editing"
        :style="{
          backgroundColor: styleOverrides.button_bg || undefined,
          color: styleOverrides.button_color || undefined,
        }"
        :class="{ 'bg-primary text-primary-foreground': !styleOverrides.button_bg, 'opacity-50 cursor-not-allowed': editing }"
      >
        {{ isSubmitting ? 'Enviando...' : (content.submit_text || 'Enviar') }}
      </button>
      <p v-if="editing" class="text-xs text-muted-foreground text-center">El formulario no envia en modo edicion</p>
    </form>
  </div>
</template>
