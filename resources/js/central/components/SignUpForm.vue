<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field"
import { Input } from "@/components/ui/input"

import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/central/stores/auth'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const router = useRouter()
const authStore = useAuthStore()
const isLoading = ref(false)
const error = ref('')

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  business_name: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const handleRegister = async () => {
  isLoading.value = true
  error.value = ''

  if (form.value.password !== form.value.password_confirmation) {
    error.value = 'Las contraseñas no coinciden'
    isLoading.value = false
    return
  }

  try {
    const data = await authStore.register(form.value)
    if (data.checkout_url) {
      window.location.href = data.checkout_url
      return
    }
    router.push({ name: 'Dashboard' })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'No pudimos completar el registro'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleRegister" :class="cn('flex flex-col gap-6', props.class)">
    <FieldGroup>
      <div class="flex flex-col gap-1.5 text-left">
        <h2 class="text-2xl font-bold tracking-tight">Crea tu cuenta</h2>
        <p class="text-muted-foreground text-sm">
          Tu espacio de trabajo estará listo cuando confirmes tu cuenta.
        </p>
      </div>

      <div
        v-if="error"
        class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
        role="alert"
      >
        {{ error }}
      </div>

      <div class="grid grid-cols-2 gap-4">
        <Field>
          <FieldLabel for="first_name">Nombre</FieldLabel>
          <Input id="first_name" v-model="form.first_name" type="text" placeholder="Juan" autocomplete="given-name" required />
        </Field>
        <Field>
          <FieldLabel for="last_name">Apellido</FieldLabel>
          <Input id="last_name" v-model="form.last_name" type="text" placeholder="Pérez" autocomplete="family-name" required />
        </Field>
      </div>

      <Field>
        <FieldLabel for="email">Correo electrónico</FieldLabel>
        <Input id="email" v-model="form.email" type="email" placeholder="tu@ejemplo.com" autocomplete="email" required />
      </Field>

      <Field>
        <FieldLabel for="business_name">Nombre del negocio</FieldLabel>
        <Input id="business_name" v-model="form.business_name" type="text" placeholder="Complejo Deportivo Los Andes" autocomplete="organization" required />
      </Field>

      <Field>
        <FieldLabel for="phone">Teléfono</FieldLabel>
        <Input id="phone" v-model="form.phone" type="tel" placeholder="+51 999 999 999" autocomplete="tel" required />
      </Field>

      <div class="grid grid-cols-2 gap-4">
        <Field>
          <FieldLabel for="password">Contraseña</FieldLabel>
          <Input id="password" v-model="form.password" type="password" autocomplete="new-password" required />
        </Field>
        <Field>
          <FieldLabel for="password_confirmation">Confirmar</FieldLabel>
          <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
        </Field>
      </div>

      <Field>
        <Button type="submit" :disabled="isLoading" class="w-full">
          <Loader2 v-if="isLoading" class="size-4 animate-spin" />
          {{ isLoading ? 'Creando cuenta…' : 'Crear cuenta' }}
        </Button>
      </Field>

      <FieldDescription class="text-center">
        ¿Ya tienes cuenta?
        <RouterLink :to="{ name: 'Login' }" class="font-medium text-foreground underline-offset-4 hover:underline">
          Iniciar sesión
        </RouterLink>
      </FieldDescription>
    </FieldGroup>
  </form>
</template>
