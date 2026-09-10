<script setup lang="ts">
import { ref } from "vue"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

const props = defineProps<{
  isLoading?: boolean
  errors?: Record<string, string>
}>()

const emit = defineEmits<{
  (e: "submit", data: any): void
}>()

const form = ref({
  first_name: "",
  last_name: "",
  email: "",
  business_name: "",
  phone: "",
  password: "",
  password_confirmation: "",
})

const submit = () => {
  emit("submit", { ...form.value })
}

defineExpose({ submit })
</script>

<template>
  <Card class="w-full relative overflow-hidden">
    <CardContent class="pt-6">
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="first_name">Nombre</Label>
            <Input
              id="first_name"
              v-model="form.first_name"
              placeholder="Juan"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.first_name" class="text-sm text-destructive">
              {{ errors.first_name }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="last_name">Apellido</Label>
            <Input
              id="last_name"
              v-model="form.last_name"
              placeholder="Pérez"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.last_name" class="text-sm text-destructive">
              {{ errors.last_name }}
            </p>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="email">Correo electrónico</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="usuario@ejemplo.com"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.email" class="text-sm text-destructive">
              {{ errors.email }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="phone">Teléfono</Label>
            <Input
              id="phone"
              v-model="form.phone"
              placeholder="+51 999 999 999"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.phone" class="text-sm text-destructive">
              {{ errors.phone }}
            </p>
          </div>
        </div>

        <div class="space-y-2">
          <Label htmlFor="business_name">Nombre del negocio</Label>
          <Input
            id="business_name"
            v-model="form.business_name"
            placeholder="Complejo Deportivo Los Andes"
            :disabled="isLoading"
            required
          />
          <p v-if="errors?.business_name" class="text-sm text-destructive">
            {{ errors.business_name }}
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="space-y-2">
            <Label htmlFor="password">Contraseña</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              :disabled="isLoading"
              required
            />
            <p v-if="errors?.password" class="text-sm text-destructive">
              {{ errors.password }}
            </p>
          </div>

          <div class="space-y-2">
            <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
            <Input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              :disabled="isLoading"
              required
            />
            <p
              v-if="errors?.password_confirmation"
              class="text-sm text-destructive"
            >
              {{ errors.password_confirmation }}
            </p>
          </div>
        </div>
      </form>
    </CardContent>
  </Card>
</template>
