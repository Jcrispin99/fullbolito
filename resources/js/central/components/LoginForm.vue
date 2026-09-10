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
import { useAuthStore } from '@central/stores/auth'
import { Loader2 } from 'lucide-vue-next'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function handleLogin() {
  loading.value = true
  error.value = null

  try {
    await authStore.login(email.value, password.value)
    router.push({ name: 'Dashboard' })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="handleLogin" :class="cn('flex flex-col gap-6', props.class)">
    <FieldGroup>
      <div class="flex flex-col gap-1.5 text-left">
        <h2 class="text-2xl font-bold tracking-tight">Bienvenido de vuelta</h2>
        <p class="text-muted-foreground text-sm">
          Ingresa tus credenciales para continuar.
        </p>
      </div>

      <div
        v-if="error"
        class="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
        role="alert"
      >
        {{ error }}
      </div>

      <Field>
        <FieldLabel for="email">Correo electrónico</FieldLabel>
        <Input
          id="email"
          v-model="email"
          type="email"
          placeholder="admin@fullbolito.com"
          autocomplete="email"
          required
        />
      </Field>

      <Field>
        <div class="flex items-center justify-between">
          <FieldLabel for="password">Contraseña</FieldLabel>
          <a
            href="#"
            class="text-xs text-muted-foreground transition hover:text-primary"
            @click.prevent
          >
            ¿Olvidaste tu contraseña?
          </a>
        </div>
        <Input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        />
      </Field>

      <Field>
        <Button type="submit" :disabled="loading" class="w-full">
          <Loader2 v-if="loading" class="size-4 animate-spin" />
          {{ loading ? 'Iniciando sesión…' : 'Iniciar sesión' }}
        </Button>
      </Field>

      <FieldDescription class="text-center">
        ¿No tienes cuenta?
        <RouterLink :to="{ name: 'Register' }" class="font-medium text-foreground underline-offset-4 hover:underline">
          Crear cuenta
        </RouterLink>
      </FieldDescription>
    </FieldGroup>
  </form>
</template>
