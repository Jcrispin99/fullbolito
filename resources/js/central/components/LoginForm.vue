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
import { ArrowRight, Eye, EyeOff, LockKeyhole, Mail, ShieldCheck, Sparkles } from 'lucide-vue-next'
import { Spinner } from '@/components/ui/spinner'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
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
    <FieldGroup class="gap-5">
      <div class="text-left">
        <div class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-primary/15 bg-primary/5 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary">
          <Sparkles class="size-3" />
          Tu panel te espera
        </div>
        <h1 class="text-balance text-3xl font-bold tracking-tight sm:text-4xl">Vuelve al juego.</h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
          Ingresa para gestionar tus canchas, reservas y próximos partidos.
        </p>
      </div>

      <div
        v-if="error"
        class="rounded-xl border border-destructive/25 bg-destructive/10 p-3.5 text-sm text-destructive"
        role="alert"
      >
        {{ error }}
      </div>

      <Field>
        <FieldLabel for="email">Correo electrónico</FieldLabel>
        <div class="relative">
          <Mail class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            id="email"
            v-model="email"
            class="h-11 rounded-xl pl-10"
            type="email"
            placeholder="admin@fullbolito.com"
            autocomplete="email"
            required
          />
        </div>
      </Field>

      <Field>
        <div class="flex items-center justify-between">
          <FieldLabel for="password">Contraseña</FieldLabel>
          <button type="button" class="text-xs text-muted-foreground transition hover:text-primary">
            ¿Olvidaste tu contraseña?
          </button>
        </div>
        <div class="relative">
          <LockKeyhole class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            id="password"
            v-model="password"
            class="h-11 rounded-xl pr-10 pl-10"
            :type="showPassword ? 'text' : 'password'"
            placeholder="Tu contraseña"
            autocomplete="current-password"
            required
          />
          <button
            type="button"
            class="absolute top-1/2 right-3 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground"
            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
            @click="showPassword = !showPassword"
          >
            <EyeOff v-if="showPassword" class="size-4" />
            <Eye v-else class="size-4" />
          </button>
        </div>
      </Field>

      <Field>
        <Button type="submit" :disabled="loading" class="h-11 w-full rounded-xl shadow-lg shadow-primary/20">
          <Spinner v-if="loading" />
          <template v-if="loading">Iniciando sesión…</template>
          <template v-else>
            Entrar a mi panel
            <ArrowRight class="size-4" />
          </template>
        </Button>
      </Field>

      <div class="rounded-xl border border-dashed border-border bg-muted/35 px-4 py-3 text-center text-sm text-muted-foreground">
        ¿Aún no juegas con nosotros?
        <RouterLink :to="{ name: 'Register' }" class="ml-1 font-semibold text-primary underline-offset-4 hover:underline">
          Crear cuenta gratis
        </RouterLink>
      </div>

      <FieldDescription class="flex items-center justify-center gap-1.5 text-center text-xs">
        <ShieldCheck class="size-3.5 text-emerald-600" />
        Acceso seguro y protegido
      </FieldDescription>
    </FieldGroup>
  </form>
</template>
