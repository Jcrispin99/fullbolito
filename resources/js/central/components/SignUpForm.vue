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
import { ArrowRight, Building2, Check, LockKeyhole, Mail, Phone, Sparkles, UserRound } from 'lucide-vue-next'
import { Spinner } from '@/components/ui/spinner'

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
    <FieldGroup class="gap-4">
      <div class="text-left">
        <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-primary/15 bg-primary/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary">
          <Sparkles class="size-3" />
          Empieza hoy
        </div>
        <h1 class="text-balance text-3xl font-bold tracking-tight sm:text-4xl">Pon tu negocio en cancha.</h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
          Crea tu espacio y empieza a recibir reservas en línea.
        </p>
      </div>

      <div
        v-if="error"
        class="rounded-xl border border-destructive/25 bg-destructive/10 p-3.5 text-sm text-destructive"
        role="alert"
      >
        {{ error }}
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <Field>
          <FieldLabel for="first_name">Nombre</FieldLabel>
          <div class="relative">
            <UserRound class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="first_name" v-model="form.first_name" class="h-11 rounded-xl pl-10" type="text" placeholder="Juan" autocomplete="given-name" required />
          </div>
        </Field>
        <Field>
          <FieldLabel for="last_name">Apellido</FieldLabel>
          <div class="relative">
            <UserRound class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="last_name" v-model="form.last_name" class="h-11 rounded-xl pl-10" type="text" placeholder="Pérez" autocomplete="family-name" required />
          </div>
        </Field>
      </div>

      <Field>
        <FieldLabel for="email">Correo electrónico</FieldLabel>
        <div class="relative">
          <Mail class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input id="email" v-model="form.email" class="h-11 rounded-xl pl-10" type="email" placeholder="tu@ejemplo.com" autocomplete="email" required />
        </div>
      </Field>

      <div class="grid gap-4 sm:grid-cols-[1.35fr_0.65fr]">
        <Field>
          <FieldLabel for="business_name">Nombre del negocio</FieldLabel>
          <div class="relative">
            <Building2 class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="business_name" v-model="form.business_name" class="h-11 rounded-xl pl-10" type="text" placeholder="Complejo Los Andes" autocomplete="organization" required />
          </div>
        </Field>

        <Field>
          <FieldLabel for="phone">Teléfono</FieldLabel>
          <div class="relative">
            <Phone class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="phone" v-model="form.phone" class="h-11 rounded-xl pl-10" type="tel" placeholder="999 999 999" autocomplete="tel" required />
          </div>
        </Field>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <Field>
          <FieldLabel for="password">Contraseña</FieldLabel>
          <div class="relative">
            <LockKeyhole class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="password" v-model="form.password" class="h-11 rounded-xl pl-10" type="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" required />
          </div>
        </Field>
        <Field>
          <FieldLabel for="password_confirmation">Confirmar</FieldLabel>
          <div class="relative">
            <Check class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input id="password_confirmation" v-model="form.password_confirmation" class="h-11 rounded-xl pl-10" type="password" placeholder="Repite tu contraseña" autocomplete="new-password" required />
          </div>
        </Field>
      </div>

      <Field>
        <Button type="submit" :disabled="isLoading" class="h-11 w-full rounded-xl shadow-lg shadow-primary/20">
          <Spinner v-if="isLoading" />
          <template v-if="isLoading">Creando tu espacio…</template>
          <template v-else>
            Crear mi cuenta
            <ArrowRight class="size-4" />
          </template>
        </Button>
      </Field>

      <FieldDescription class="text-center">
        ¿Ya tienes cuenta?
        <RouterLink :to="{ name: 'Login' }" class="font-semibold text-primary underline-offset-4 hover:underline">
          Iniciar sesión
        </RouterLink>
      </FieldDescription>
    </FieldGroup>
  </form>
</template>
